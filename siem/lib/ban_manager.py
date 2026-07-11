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


def build_ban_rule_args(ip, comment):
    return ["iptables", "-A", "INPUT", "-s", ip, "-j", "DROP",
            "-m", "comment", "--comment", comment]


def to_unban_args(ban_rule_args):
    """Transforme la commande -A stockée en -D (même spec de règle == même match)."""
    args = ban_rule_args.copy()
    args[args.index("-A")] = "-D"
    return args


class BanManager:
    def __init__(self, logger, rate_limiter, use_sudo=True,
                 ban_duration_sec=1800, unban_check_interval_sec=30,
                 state_file="/opt/siem/var/banned_ips.json",
                 on_event=None):
        """
        on_event: callback(dict) optionnel, appelé pour chaque événement
        ban/unban (ex: écrire une alerte JSONL). Doit être thread-safe si
        fourni, car appelé depuis le thread de fond.
        """
        self.logger = logger
        self.rate_limiter = rate_limiter
        self.use_sudo = use_sudo
        self.ban_duration_sec = ban_duration_sec
        self.unban_check_interval_sec = unban_check_interval_sec
        self.state_file = Path(state_file)
        self.on_event = on_event

        self._lock = threading.Lock()
        self._stop_event = threading.Event()
        self._thread = None
        self.banned_ips = self._load_state()

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
        """Doit être appelée avec self._lock déjà acquis."""
        try:
            self.state_file.parent.mkdir(parents=True, exist_ok=True)
            tmp = self.state_file.with_suffix(".tmp")
            with tmp.open("w") as f:
                json.dump(self.banned_ips, f, indent=2)
            tmp.replace(self.state_file)
        except Exception as e:
            self.logger.error(f"[STATE] échec écriture {self.state_file}: {e}")

    # ---------- iptables ----------

    def _run_iptables(self, cmd_args, label):
        cmd = (["sudo"] if self.use_sudo else []) + cmd_args
        try:
            subprocess.run(cmd, check=True)
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

        rule_args = build_ban_rule_args(ip, comment)
        self.logger.critical(f"[BLOCAGE] {ip} -> {count}")
        if not self._run_iptables(rule_args, "BAN"):
            return "FAIL_BAN"

        now = time.time()
        with self._lock:
            # re-check : une autre IP a pu être traitée entre-temps par le thread de fond,
            # mais pas cette IP précise en ban (pas de unban concurrent possible ici)
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

    def _process_expired(self):
        now = time.time()
        with self._lock:
            expired = [ip for ip, info in self.banned_ips.items() if now >= info["expires_at"]]

        for ip in expired:
            if not self.rate_limiter.allow():
                self.logger.warning(f"[RATE_LIMIT] unban de {ip} reporté (limite atteinte)")
                continue

            with self._lock:
                info = self.banned_ips.get(ip)
            if info is None:
                continue  # déjà débanni entre-temps (ex: extend concurrent)

            ok = self._run_iptables(to_unban_args(info["rule_args"]), "UNBAN")
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
