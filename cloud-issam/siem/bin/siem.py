#!/usr/bin/env python3
"""
Détection brute-force SSH avec ban/unban automatique et rate limiting.
Fichier d'orchestration principal — la logique de ban/unban vit dans ban_manager.py
(BanManager, avec son propre thread de fond pour les unbans), le rate limiting dans
rate_limiter.py (RateLimiter).
"""
import re
import subprocess
import json
import sys
import threading
import time
from collections import defaultdict
from pathlib import Path
from datetime import datetime
sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from lib.siem_core import load_config, setup_logging
from lib.ip_public import is_public
from lib.comment_builder import iptables_comment
from lib.rate_limit import RateLimiter
from lib.ban_manager import BanManager
from lib.logrotate import rotate_if_full

# Intervalle de purge du dict `fails` pour les IP inactives (fix leak mémoire)
FAILS_CLEANUP_INTERVAL_SEC = 300


def main():
    config, args = load_config()
    logger = setup_logging(config)
    fails = defaultdict(list)
    last_cleanup = [time.time()]  # liste pour pouvoir muter depuis process_line (closure)

    """ON charge toute les config"""
    PATTERNS = [re.compile(p) for p in config.get('FAIL_PATTERNS', [])]
    SEUIL = config['SEUIL']
    LOG_FILE = config['LOG_FILE']
    ACTION = config['ACTION']
    # FIX: la clé YAML est en minuscules ("whitelist:") dans les deux configs,
    # mais le code cherchait "WHITELIST" -> toujours [] -> whitelist inopérante.
    # On accepte les deux variantes pour rester compatible avec l'existant.
    WHITELIST = config.get('WHITELIST', config.get('whitelist', []))
    JSON_OUT = Path(config['OUTPUT_JSON'])
    TAG = config['COMMENT_TAG']
    WINDOW_SEC = config.get('WINDOW_SEC', 300)
    USE_SUDO = config.get('USE_SUDO', True)
    BAN_DURATION_SEC = config.get('BAN_DURATION_SEC', 1800)
    UNBAN_CHECK_INTERVAL_SEC = config.get('UNBAN_CHECK_INTERVAL_SEC', 30)
    RATE_LIMIT_MAX_ACTIONS = config.get('RATE_LIMIT_MAX_ACTIONS', 10)
    RATE_LIMIT_WINDOW_SEC = config.get('RATE_LIMIT_WINDOW_SEC', 60)
    # FIX: rate limiter dédié à l'unban, pour ne pas être affamé par les bans
    # en cas d'attaque massive (sinon banned_ips grossit sans jamais purger).
    UNBAN_RATE_LIMIT_MAX_ACTIONS = config.get('UNBAN_RATE_LIMIT_MAX_ACTIONS', RATE_LIMIT_MAX_ACTIONS)
    UNBAN_RATE_LIMIT_WINDOW_SEC = config.get('UNBAN_RATE_LIMIT_WINDOW_SEC', RATE_LIMIT_WINDOW_SEC)
    RESET_BAN_ON_REPEAT = config.get('RESET_BAN_ON_REPEAT', True)
    STATE_FILE = config.get('BAN_STATE_FILE')

    JSON_OUT.parent.mkdir(parents=True, exist_ok=True)

    # Écriture JSONL protégée par lock : appelée depuis le thread principal
    # (détections) ET depuis le thread de fond du BanManager (unbans).
    alert_lock = threading.Lock()
    def write_alert(fields):
        rotate_if_full(JSON_OUT,config['BACKUP_ALERT'])
        alert = {
            "timestamp": datetime.now().isoformat(),
            "rule_id": TAG,
            "log_source": LOG_FILE,
            **fields,
        }
        with alert_lock:
            with JSON_OUT.open('a') as jsonf:
                jsonf.write(json.dumps(alert) + "\n")

    ban_rate_limiter = RateLimiter(RATE_LIMIT_MAX_ACTIONS, RATE_LIMIT_WINDOW_SEC)
    unban_rate_limiter = RateLimiter(UNBAN_RATE_LIMIT_MAX_ACTIONS, UNBAN_RATE_LIMIT_WINDOW_SEC)
    ban_manager = BanManager(
        logger=logger,
        rate_limiter=ban_rate_limiter,
        unban_rate_limiter=unban_rate_limiter,
        use_sudo=USE_SUDO,
        ban_duration_sec=BAN_DURATION_SEC,
        unban_check_interval_sec=UNBAN_CHECK_INTERVAL_SEC,
        state_file=STATE_FILE,
        on_event=write_alert,
        config=config,
    )

    logger.info(
        f"[J12 PROD] {len(PATTERNS)} règles | SEUIL={SEUIL} | FENETRE={WINDOW_SEC}s | "
        f"ACTION={ACTION} | BAN_DURATION={BAN_DURATION_SEC}s | "
        f"RATE_LIMIT={RATE_LIMIT_MAX_ACTIONS}/{RATE_LIMIT_WINDOW_SEC}s | "
        f"UNBAN_RATE_LIMIT={UNBAN_RATE_LIMIT_MAX_ACTIONS}/{UNBAN_RATE_LIMIT_WINDOW_SEC}s | "
        f"WHITELIST={WHITELIST}"
    )
    ban_manager.start() # thread de fond : vérifie/exécute les unbans en parallèle

    def cleanup_fails_if_due():
        """Purge les IP du dict `fails` qui n'ont plus aucun timestamp dans la
        fenêtre glissante. Appelée depuis le thread principal (process_line),
        donc pas besoin de lock sur `fails` (un seul thread le manipule)."""
        now = time.time()
        if now - last_cleanup[0] < FAILS_CLEANUP_INTERVAL_SEC:
            return
        stale_ips = [ip for ip, ts in fails.items() if not any(now - t < WINDOW_SEC for t in ts)]
        for ip in stale_ips:
            del fails[ip]
        last_cleanup[0] = now
        if stale_ips:
            logger.info(f"[CLEANUP] {len(stale_ips)} IP purgées du tracker | {len(fails)} restantes en mémoire")

    # --- NOUVEAU: Fonction pour traiter 1 ligne ---
    def process_line(line):
        ip = None
        matched_rule = "unknown"
        for i, regex in enumerate(PATTERNS):
            if match := regex.search(line):
                groups = match.groups()
                ip = groups[0] # <- maintenant IP est toujours groupe 1
                matched_rule = f"rule_{i+1}"
                logger.info(f"MATCH {matched_rule} IP={ip} LINE={line.strip()[:80]}")
                break

        if not ip:
            return

        # try:
        #     #if not is_public(ip):
        #         #return
        #         print("pass")
        # except ValueError:
        #     logger.warning(f"[IP INVALIDE] '{ip}' ignorée (regex probablement fautive)")
        #     return

        now = time.time()
        fails[ip] = [t for t in fails[ip] if now - t < WINDOW_SEC]
        fails[ip].append(now)
        count = len(fails[ip])

        # Détecter si c'est SSH ou WEB pour le event_type
        event_type = "brute_force_ssh" if "auth.log" in LOG_FILE else "web_attack"
        action_taken = "WATCH"
        comment = iptables_comment(TAG, LOG_FILE, count)

        if ban_manager.is_banned(ip):
            if RESET_BAN_ON_REPEAT:
                ban_manager.extend(ip)
                action_taken = "BAN_EXTENDED"
            else:
                action_taken = "ALREADY_BANNED"
        elif count >= SEUIL:
            if ip in WHITELIST:
                logger.warning(f"[SAFE] {ip} est en WHITELIST. Count={count}")
                action_taken = "SAFE"
            elif ACTION!= "block":
                logger.warning(f"[ALERTE] {ip} -> {count} | DRY-RUN")
                action_taken = "DRY_RUN"
                fails[ip].clear()
            else:
                action_taken = ban_manager.ban(ip, comment, count)
                fails[ip].clear()

        # On log à chaque hit, pas seulement au ban
        write_alert({
            "event_type": event_type,
            "src_ip": ip,
            "count": count,
            "action": action_taken,
            "rule": matched_rule,
            "raw_log": line.strip(), # <- très utile pour debug
            "comment": comment,
        })

        # FIX: purge périodique du tracker `fails` pour éviter la croissance
        # non bornée (chaque IP qui n'atteint jamais le seuil restait en RAM
        # indéfiniment -> OOM kill à terme, surtout côté web où le nombre
        # d'IP uniques par jour est élevé).
        cleanup_fails_if_due()

    # --- NOUVEAU: Rattrapage des 200 dernières lignes au démarrage ---
    # --- RATTRAPAGE: Lecture des 100 dernières lignes sans charger tout en RAM ---
    logger.info(f"[RATTRAPAGE] Lecture des 100 dernières lignes de {LOG_FILE}")
    try:
        from collections import deque
        with open(LOG_FILE, 'r') as f:
            last_lines = deque(f, maxlen=20) # <- ne garde que les 100 dernières
            for line in last_lines:
                process_line(line)
    except FileNotFoundError:
        logger.warning(f"[RATTRAPAGE] Fichier {LOG_FILE} introuvable")

    # --- MODIFIE: tail avec --retry pour suivre même si fichier rotaté ---
    logger.info(f"[LIVE] Suivi de {LOG_FILE}")
    proc = subprocess.Popen(
        ["tail", "-F", "--retry", LOG_FILE],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        bufsize=1,
        universal_newlines=True
    )

    try:
        for line in proc.stdout:
            process_line(line)
    except Exception:
        logger.exception("[CRASH] Boucle principale interrompue")
    finally:
        stderr_out = proc.stderr.read() if proc.stderr else ""
        if stderr_out:
            logger.error(f"[TAIL STDERR] {stderr_out.strip()}")
        proc.terminate()
        ban_manager.stop()

if __name__ == "__main__":
    main()
