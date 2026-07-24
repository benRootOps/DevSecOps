FROM python:3.10-slim

WORKDIR /app

COPY deps/ /deps
COPY siem/requirements.txt .

# Installe les dépendances Python depuis le cache local (pip)
RUN pip install --no-index --find-links=/deps -r requirements.txt

# Supprime le cache pip pour alléger l'image
RUN rm -rf /deps

# FIX: installe sudo + iptables depuis un cache .deb local (siem/deps-system/),
# sans accès réseau au moment du build. dpkg -i installe tous les .deb présents ;
# apt-get install -f corrige l'ordre des dépendances en utilisant uniquement
# le cache apt déjà peuplé (aucun appel réseau si tous les .deb nécessaires
# sont bien dans le dossier).
COPY siem/deps-system/*.deb /tmp/debs/
RUN dpkg -i /tmp/debs/*.deb || apt-get install -f -y --no-install-recommends && \
    rm -rf /tmp/debs /var/lib/apt/lists/*

COPY siem/ .

RUN useradd -m -u 1001 appuser && chown -R appuser:appuser /app

# FIX: autorise appuser (non-root) à exécuter UNIQUEMENT iptables via sudo,
# sans mot de passe. Pas d'accès sudo complet - juste cette commande précise,
# nécessaire pour la gestion des bans/unbans par ban_manager.py.
RUN echo "appuser ALL=(root) NOPASSWD: /usr/sbin/iptables" > /etc/sudoers.d/siem-iptables && \
    chmod 0440 /etc/sudoers.d/siem-iptables

USER appuser

CMD ["python", "bin/siem.py", "--config", "etc/config.yaml"]
