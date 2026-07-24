"""

Protège les actions iptables (ban/unban) contre un flood : indépendant du
seuil de détection (SEUIL), il limite juste le nombre d'appels subprocess
déclenchés sur une fenêtre de temps donnée, quelle que soit leur origine
(thread principal pour les bans, thread de fond pour les unbans).
"""

import threading
import time
from collections import deque


class RateLimiter:
    def __init__(self, max_actions, window_sec):
        self.max_actions = max_actions
        self.window_sec = window_sec
        self.actions = deque()
        self._lock = threading.Lock()

    def allow(self):
        """Retourne True si une action peut être exécutée maintenant, et
        l'enregistre dans ce cas. Thread-safe."""
        now = time.time()
        with self._lock:
            while self.actions and now - self.actions[0] > self.window_sec:
                self.actions.popleft()
            if len(self.actions) < self.max_actions:
                self.actions.append(now)
                return True
            return False
