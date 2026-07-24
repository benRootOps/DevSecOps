"""
ban_manager.py

Gère le bannissement des IP via ipset (plutôt que des règles iptables brutes),
ce qui permet un TTL natif : une IP bannie est automatiquement débannie par
le noyau après expiration, sans cron ni tâche de nettoyage à maintenir.

Prérequis système : le paquet `ipset` doit être installé.
    sudo apt install ipset

Fonctionnement :
    1. Un ipset nommé (ex: "siem_banned_ips") est créé une seule fois au démarrage,
       de type hash:ip avec un timeout par défaut.
    2. Une règle iptables unique référence cet ipset ("-m set --match-set ... -j DROP").
       On ne rajoute JAMAIS de règle iptables par IP : une seule règle suffit pour tout l'ipset.
    3. Chaque ban ajoute juste une entrée dans l'ipset avec son propre timeout.
    4. Le noyau retire automatiquement l'entrée expirée -> unban automatique, gratuit.
"""

import subprocess
import logging


class BanManager:
    def __init__(self, set_name: str, default_ttl_sec: int, logger: logging.Logger):
        self.set_name = set_name
        self.default_ttl_sec = default_ttl_sec
        self.logger = logger

    # ------------------------------------------------------------------
    # Setup (à appeler une fois au démarrage du service)
    # ------------------------------------------------------------------

    def ensure_ipset(self):
        """Crée l'ipset s'il n'existe pas déjà. Idempotent : safe à rappeler."""
        check = subprocess.run(
            ["ipset", "list", "-name", self.set_name],
            capture_output=True, text=True
        )
        if check.returncode == 0:
            self.logger.info(f"[BAN_MANAGER] ipset '{self.set_name}' déjà existant")
            return

        cmd = [
            "ipset", "create", self.set_name, "hash:ip",
            "timeout", str(self.default_ttl_sec)
        ]
        result = subprocess.run(cmd, capture_output=True, text=True)
        if result.returncode != 0:
            raise RuntimeError(
                f"Impossible de créer l'ipset '{self.set_name}': {result.stderr.strip()}"
            )
        self.logger.info(
            f"[BAN_MANAGER] ipset '{self.set_name}' créé (timeout défaut={self.default_ttl_sec}s)"
        )

    def ensure_iptables_rule(self):
        """
        Ajoute la règle iptables référençant l'ipset, une seule fois.
        Vérifie d'abord si elle existe déjà pour rester idempotent (évite les doublons
        à chaque redémarrage du service).
        """
        check_cmd = [
            "iptables", "-C", "INPUT",
            "-m", "set", "--match-set", self.set_name, "src",
            "-j", "DROP"
        ]
        check = subprocess.run(check_cmd, capture_output=True, text=True)
        if check.returncode == 0:
            self.logger.info(f"[BAN_MANAGER] Règle iptables pour '{self.set_name}' déjà présente")
            return

        add_cmd = [
            "iptables", "-A", "INPUT",
            "-m", "set", "--match-set", self.set_name, "src",
            "-j", "DROP"
        ]
        result = subprocess.run(add_cmd, capture_output=True, text=True)
        if result.returncode != 0:
            raise RuntimeError(
                f"Impossible d'ajouter la règle iptables pour '{self.set_name}': {result.stderr.strip()}"
            )
        self.logger.info(f"[BAN_MANAGER] Règle iptables ajoutée pour '{self.set_name}'")

    def setup(self):
        """Raccourci pratique : initialise ipset + règle iptables en un appel."""
        self.ensure_ipset()
        self.ensure_iptables_rule()

    # ------------------------------------------------------------------
    # Opérations courantes (appelées à chaque détection)
    # ------------------------------------------------------------------

    def is_banned(self, ip: str) -> bool:
        """Retourne True si l'IP est actuellement présente (donc bannie) dans l'ipset."""
        result = subprocess.run(
            ["ipset", "test", self.set_name, ip],
            capture_output=True, text=True
        )
        return result.returncode == 0

    def ban(self, ip: str, ttl_sec: int = None) -> bool:
        """
        Bannit une IP avec un TTL donné (ou le TTL par défaut si non précisé).
        Utilise '-exist' pour ne pas planter si l'IP est déjà présente
        (rafraîchit simplement son timeout).
        Retourne True si le ban a réussi.
        """
        ttl = ttl_sec if ttl_sec is not None else self.default_ttl_sec
        cmd = ["ipset", "add", self.set_name, ip, "timeout", str(ttl), "-exist"]
        result = subprocess.run(cmd, capture_output=True, text=True)
        if result.returncode != 0:
            self.logger.error(f"[BAN_MANAGER] Échec du ban pour {ip}: {result.stderr.strip()}")
            return False
        self.logger.info(f"[BAN_MANAGER] {ip} banni pour {ttl}s")
        return True

    def unban(self, ip: str) -> bool:
        """Débannit une IP manuellement avant expiration (whitelist tardive, erreur, etc.)."""
        cmd = ["ipset", "del", self.set_name, ip]
        result = subprocess.run(cmd, capture_output=True, text=True)
        if result.returncode != 0:
            # Pas grave si l'IP n'était déjà plus dans le set (déjà expirée)
            self.logger.warning(f"[BAN_MANAGER] Unban {ip} : {result.stderr.strip()}")
            return False
        self.logger.info(f"[BAN_MANAGER] {ip} débanni manuellement")
        return True

    def list_banned(self):
        """Retourne la liste des IP actuellement bannies (utile pour un endpoint de monitoring/API)."""
        result = subprocess.run(
            ["ipset", "list", self.set_name],
            capture_output=True, text=True
        )
        if result.returncode != 0:
            self.logger.error(f"[BAN_MANAGER] Impossible de lister '{self.set_name}': {result.stderr.strip()}")
            return []

        # Le format de sortie liste les IP après une ligne "Members:"
        lines = result.stdout.splitlines()
        try:
            idx = lines.index("Members:")
        except ValueError:
            return []
        return [line.split()[0] for line in lines[idx + 1:] if line.strip()]
