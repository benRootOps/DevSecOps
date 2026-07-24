"""
Gestion du cycle de vie des bans iptables : ban, unban automatique,
persistance d'état, thread de fond dédié à la vérification des expirations.

Toutes les méthodes publiques (is_banned, ban, extend) sont thread-safe :
elles peuvent être appelées depuis le thread principal (lecture du log)
pendant que le thread de fond (_run_loop) traite les unbans en parallèle.
"""

import json
import subprocess
import threading
import time
from pathlib import Path
from lib.logrotate import rotate_if_full


def build_ban_rule_args(ip, comment, chain="DOCKER-USER"):
    return [
        "iptables",
        "-I",
        chain,
        "-s",
        ip,
        "-j",
        "DROP",
        "-m",
        "comment",
        "--comment",
        comment
    ]


def to_unban_args(ban_rule_args):
    """Transforme la commande -A stockée en -D (même spec de règle == même match)."""
    args = ban_rule_args.copy()

    if "-A" in args:
        args[args.index("-A")] = "-D"
    elif "-I" in args:
        args[args.index("-I")] = "-D"

    return args


def to_check_args(ban_rule_args):
    """Transforme la commande -A stockée en -C (vérifie l'existence sans modifier)."""
    args = ban_rule_args.copy()

    if "-A" in args:
        args[args.index("-A")] = "-C"
    elif "-I" in args:
        args[args.index("-I")] = "-C"

    return args

class BanManager:
    def __init__(self, logger, rate_limiter, use_sudo=True,
                 ban_duration_sec=1800, unban_check_interval_sec=30,
                 state_file="/app/var/banned_ips.jsonl",
                 on_event=None, config=None, unban_rate_limiter=None):
        """
        on_event: callback(dict) optionnel, appelé pour chaque événement
        ban/unban (ex: écrire une alerte JSONL). Doit être thread-safe si
        fourni, car appelé depuis le thread de fond.

        unban_rate_limiter: FIX — rate limiter dédié aux unbans, séparé de
        celui des bans. Avant ce fix, les deux opérations partageaient le
        même compteur : en cas d'attaque massive, les bans saturaient le
        quota et les unbans expirés restaient bloqués indéfiniment, faisant
        grossir banned_ips (et la mémoire) sans jamais purger. Si non fourni,
        on retombe sur le même rate_limiter que pour les bans (comportement
        historique, pour compatibilité ascendante).
        """
        self.logger = logger
        self.config = config
        self.rate_limiter = rate_limiter
        self.unban_rate_limiter = unban_rate_limiter or rate_limiter
        self.use_sudo = use_sudo
        self.ban_duration_sec = ban_duration_sec
        self.unban_check_interval_sec = unban_check_interval_sec
        self.state_file = Path(state_file)
        self.on_event = on_event
        self.ban_chain = config.get("BAN_CHAIN", "INPUT")
        self._lock = threading.Lock()
        self._stop_event = threading.Event()
        self._thread = None
        self.banned_ips = self._load_state()

        # Purge les entrées dont la règle iptables n'existe plus réellement
        # (ex: redémarrage machine ayant vidé iptables, mais l'état JSON survit).
        self._reconcile_state()

    # ---------- persistance ----------

    def _load_state(self):
        if not self.state_file.exists():
            return {}
        try:
            with self.state_file.open() as f:
                raw = json.load(f)
            self.logger.info(f"[STATE] {len(raw)} IP bannie(s) rechargée(s) depuis {self.state_file}")
            return raw
        except Exception as e:
            self.logger.error(f"[STATE] échec lecture {self.state_file}: {e}")
            return {}

    def _save_state(self):
        """Doit être appelée AVEC self._lock déjà acquis."""
        backup_dir = self.config['BACKUP_BANNED_IPS']
        rotate_if_full(self.state_file, backup_dir, max_mb=10) 

        if not self.state_file.exists():
            self.banned_ips = {}  # Reset RAM si rotate

        try:
            self.state_file.parent.mkdir(parents=True, exist_ok=True)
            tmp = self.state_file.with_suffix(".tmp")
            # On copie sous lock pour éviter RuntimeError
            data_to_dump = dict(self.banned_ips)
            with tmp.open("w") as f:
                json.dump(data_to_dump, f, indent=2)  # Dump la copie
            tmp.replace(self.state_file)
        except Exception as e:
            self.logger.critical(f"[STATE] échec écriture {self.state_file}: {e}")

    # ---------- réconciliation état <-> iptables réel ----------

    def _reconcile_state(self):
        """Vérifie, au démarrage, que chaque IP en état 'bannie' correspond
        bien à une règle iptables encore présente. Purge les entrées orphelines
        pour éviter des tentatives d'unban qui échoueront indéfiniment."""
        stale = []
        for ip, info in self.banned_ips.items():
            rule_args = info.get("rule_args")
            if not rule_args or not self._rule_exists(rule_args):
                stale.append(ip)

        if not stale:
            return

        for ip in stale:
            self.banned_ips.pop(ip, None)

        self.logger.warning(
            f"[STATE] {len(stale)} entrée(s) obsolète(s) purgée(s) au démarrage "
            f"(règle iptables absente): {stale}"
        )
        with self._lock:
            self._save_state()

    # ---------- iptables ----------

    def _rule_exists(self, rule_args):
        """Vérifie via 'iptables -C' si la règle existe réellement, sans la modifier.
        Retourne True si présente, False sinon (y compris en cas d'erreur d'exécution)."""
        cmd = (["sudo"] if self.use_sudo else []) + to_check_args(rule_args)
        try:
            result = subprocess.run(cmd, capture_output=True, text=True)
            return result.returncode == 0
        except Exception as e:
            self.logger.error(f"[ERREUR IPTABLES:CHECK] {e}")
            return False

    def _run_iptables(self, cmd_args, label):
        cmd = (["sudo"] if self.use_sudo else []) + cmd_args
        try:
            subprocess.run(cmd, check=True, capture_output=True, text=True)
            return True
        except Exception as e:
            self.logger.error(f"[ERREUR IPTABLES:{label}] {e}")
            return False

    def _emit(self, event):
        if self.on_event:
            try:
                self.on_event(event)
            except Exception as e:
                self.logger.error(f"[on_event] échec callback: {e}")

    # ---------- API publique (thread principal) ----------

    def is_banned(self, ip):
        with self._lock:
            return ip in self.banned_ips

    def ban(self, ip, comment, count):
        """Tente de bannir ip. Retourne une string décrivant l'action effectuée."""
        with self._lock:
            if ip in self.banned_ips:
                return "ALREADY_BANNED"

        if not self.rate_limiter.allow():
            self.logger.warning(f"[RATE_LIMIT] ban de {ip} reporté (limite atteinte)")
            return "RATE_LIMITED"

        rule_args = build_ban_rule_args(ip,comment,self.ban_chain)
        self.logger.critical(f"[BLOCAGE] {ip} -> {count}")

        if self._rule_exists(rule_args):
            # État désynchronisé : la règle existe déjà (ex: crash précédent
            # avant écriture de l'état). On évite un -A redondant.
            self.logger.warning(f"[BAN] règle déjà présente pour {ip}, pas de -A redondant")
        elif not self._run_iptables(rule_args, "BAN"):
            return "FAIL_BAN"

        now = time.time()
        with self._lock:
            self.banned_ips[ip] = {
                "banned_at": now,
                "expires_at": now + self.ban_duration_sec,
                "rule_args": rule_args,
                "count": count,
            }
            self._save_state()
        return "DROP"

    def extend(self, ip):
        """Prolonge le ban d'une IP qui retente pendant qu'elle est bannie."""
        with self._lock:
            if ip not in self.banned_ips:
                return False
            self.banned_ips[ip]["expires_at"] = time.time() + self.ban_duration_sec
            self._save_state()
        self.logger.warning(f"[BAN_EXTENDED] {ip} retente pendant son ban, prolongation")
        return True

    # ---------- thread de fond ----------

    def start(self):
        """Démarre le thread daemon de vérification des unbans."""
        self._thread = threading.Thread(
            target=self._run_loop, name="ban-unban-checker", daemon=True
        )
        self._thread.start()
        self.logger.info(
            f"[THREAD] ban-unban-checker démarré (intervalle={self.unban_check_interval_sec}s)"
        )

    def stop(self, timeout=5):
        """Arrête proprement le thread de fond."""
        self._stop_event.set()
        if self._thread:
            self._thread.join(timeout=timeout)

    def _run_loop(self):
        # .wait(timeout) retourne dès que stop() est appelé, sans attendre
        # la fin de l'intervalle -> arrêt réactif au lieu d'un sleep bloquant.
        while not self._stop_event.wait(self.unban_check_interval_sec):
            self._process_expired()

    def _do_unban(self, ip, info):
        """Lève le ban pour ip. Retourne True si l'IP peut être considérée
        comme débannie (règle supprimée OU déjà absente), False sinon."""
        rule_args = info["rule_args"]

        if not self._rule_exists(rule_args):
            # Rien à supprimer : la règle a déjà disparu (redémarrage machine,
            # purge manuelle iptables -F, etc.). On traite comme un succès
            # pour nettoyer l'état, sans jamais appeler iptables -D dessus.
            self.logger.warning(
                f"[UNBAN] {ip}: règle iptables déjà absente, nettoyage de l'état sans appel iptables"
            )
            return True

        return self._run_iptables(to_unban_args(rule_args), "UNBAN")

    def _process_expired(self):
        now = time.time()
        with self._lock:
            expired = [ip for ip, info in self.banned_ips.items() if now >= info["expires_at"]]

        for ip in expired:
            # FIX: rate limiter dédié à l'unban (voir __init__) au lieu de
            # self.rate_limiter, pour ne pas être bloqué par une vague de bans.
            if not self.unban_rate_limiter.allow():
                self.logger.warning(f"[RATE_LIMIT] unban de {ip} reporté (limite atteinte)")
                continue

            with self._lock:
                info = self.banned_ips.get(ip)
            if info is None:
                continue  # déjà débanni entre-temps (ex: extend concurrent)

            ok = self._do_unban(ip, info)
            if ok:
                with self._lock:
                    self.banned_ips.pop(ip, None)
                    self._save_state()
                self.logger.info(f"[UNBAN] {ip} débanni (ban expiré)")

            self._emit({
                "event_type": "unban_ssh",
                "src_ip": ip,
                "action": "UNBAN" if ok else "UNBAN_FAIL",
            })
