#!/usr/bin/env python3
"""
Détection brute-force SSH avec ban/unban automatique et rate limiting.

Fichier d'orchestration principal — la logique de ban/unban vit dans
ban_manager.py (BanManager, avec son propre thread de fond pour les
unbans), le rate limiting dans rate_limiter.py (RateLimiter).
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


def main():
    config, args = load_config()
    logger = setup_logging(config)

    fails = defaultdict(list)


    """ON charge toute les config"""
    PATTERNS = [re.compile(p) for p in config.get('FAIL_PATTERNS', [])]
    SEUIL = config['SEUIL']
    LOG_FILE = config['LOG_FILE']
    ACTION = config['ACTION']
    WHITELIST = config.get('WHITELIST', [])
    JSON_OUT = Path(config['OUTPUT_JSON'])
    TAG = config['COMMENT_TAG']
    WINDOW_SEC = config.get('WINDOW_SEC', 300)
    USE_SUDO = config.get('USE_SUDO', True)
    BAN_DURATION_SEC = config.get('BAN_DURATION_SEC', 1800)
    UNBAN_CHECK_INTERVAL_SEC = config.get('UNBAN_CHECK_INTERVAL_SEC', 30)
    RATE_LIMIT_MAX_ACTIONS = config.get('RATE_LIMIT_MAX_ACTIONS', 10)
    RATE_LIMIT_WINDOW_SEC = config.get('RATE_LIMIT_WINDOW_SEC', 60)
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

    rate_limiter = RateLimiter(RATE_LIMIT_MAX_ACTIONS, RATE_LIMIT_WINDOW_SEC)
    ban_manager = BanManager(
        logger=logger,
        rate_limiter=rate_limiter,
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
        f"RATE_LIMIT={RATE_LIMIT_MAX_ACTIONS}/{RATE_LIMIT_WINDOW_SEC}s | WHITELIST={WHITELIST}"
    )

    ban_manager.start()  # thread de fond : vérifie/exécute les unbans en parallèle

    proc = subprocess.Popen(
        ["tail", "-F", LOG_FILE],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        bufsize=1
    )

    try:
        for line in proc.stdout:
            ip = None
            for regex in PATTERNS:
                if match := regex.search(line):
                    groups = match.groups()
                    ip = groups[1] if len(groups) == 2 else groups[0]
                    break

            if not ip:
                continue

            try:
                #if not is_public(ip):
                    #continue
                    print("On décommente en PROD")
            except ValueError:
                logger.warning(f"[IP INVALIDE] '{ip}' ignorée (regex probablement fautive)")
                continue

            now = time.time()
            fails[ip] = [t for t in fails[ip] if now - t < WINDOW_SEC]
            fails[ip].append(now)
            count = len(fails[ip])

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
                elif ACTION != "block":
                    logger.warning(f"[ALERTE] {ip} -> {count} | DRY-RUN")
                    action_taken = "DRY_RUN"
                    fails[ip].clear()
                else:
                    action_taken = ban_manager.ban(ip, comment, count)
                    fails[ip].clear()

            write_alert({
                "event_type": "brute_force_ssh", "src_ip": ip, "count": count,
                "action": action_taken, "comment": comment,
            })

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
