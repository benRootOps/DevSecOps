# SIEM — Détection Brute-Force SSH & Réponse Automatique

Outil de surveillance en temps réel des tentatives de connexion SSH échouées, avec détection de brute-force, bannissement automatique via `iptables`, unban après expiration, et rate limiting des actions de sécurité.

Conçu pour un déploiement en production sous forme de service `systemd`.

---

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Architecture](#architecture)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Déploiement en tant que service systemd](#déploiement-en-tant-que-service-systemd)
- [Format des alertes (`alert.jsonl`)](#format-des-alertes-alertjsonl)
- [Utilisation / exploitation](#utilisation--exploitation)
- [Tests en local](#tests-en-local)
- [Dépannage](#dépannage)
- [Sécurité](#sécurité)
- [Limitations connues](#limitations-connues)
- [Roadmap](#roadmap)

---

## Fonctionnalités

- **Surveillance en continu** d'un fichier de log (`auth.log` ou équivalent) via `tail -F`, résilient à la rotation de logs.
- **Détection de brute-force** par fenêtre glissante configurable (nombre de tentatives / temps).
- **Filtrage des IP publiques** : ignore par défaut le trafic depuis des IP privées (RFC 1918), avec option de bypass pour les tests.
- **Whitelist** d'IP exemptées de bannissement.
- **Bannissement automatique** via `iptables` (règle `DROP` taguée par un commentaire identifiable).
- **Unban automatique** après expiration du ban, exécuté dans un **thread dédié** indépendant de la boucle de lecture du log.
- **Rate limiting** des actions `iptables` (ban et unban) pour éviter tout flood d'appels système.
- **Persistance d'état** des IP bannies (fichier JSON), survit à un redémarrage du service.
- **Journal d'alertes structuré** au format JSON Lines (`alert.jsonl`), exploitable par un SIEM externe, un dashboard ou `jq`.
- **Mode DRY-RUN** (`ACTION: alert`) pour tester la détection sans bannir réellement.

---

## Architecture

```
/opt/siem/
├── bin/
│   └── siem.py              # Point d'entrée — orchestration, lecture du log, détection
├── lib/
│   ├── __init__.py
│   ├── siem_core.py          # Chargement config YAML + arguments CLI, setup logging
│   ├── ip_public.py           # is_public(ip) — détection IP publique vs privée
│   ├── comment_builder.py     # Génération du commentaire iptables (traçabilité des règles)
│   ├── ban_manager.py         # BanManager — ban/unban, persistance, thread de fond
│   └── rate_limiter.py        # RateLimiter — fenêtre glissante thread-safe
├── etc/
│   └── config.yaml            # Configuration générale
├── var/
│   ├── alert.jsonl             # Journal d'alertes (sortie JSON Lines)
│   └── banned_ips.json        # État persistant des IP actuellement bannies
│             
├── commande_SOC.txt            # Aide-mémoire commandes d'exploitation SOC
└── siem.service                 # Unit systemd
```

### Flux de traitement

```
auth.log ──(tail -F)──▶ siem.py ──▶ regex FAIL_PATTERNS ──▶ extraction IP
                                          │
                              is_public(ip) ? (sauf ALLOW_PRIVATE_IPS)
                                          │ oui
                          fenêtre glissante WINDOW_SEC ──▶ count
                                          │
                        count >= SEUIL ET ip pas déjà bannie ?
                                          │
                    WHITELIST ?──oui──▶ SAFE (log uniquement)
                                          │ non
                    ACTION=alert ?──oui──▶ DRY_RUN
                                          │ non
                    RateLimiter.allow() ?──non──▶ RATE_LIMITED
                                          │ oui
                              BanManager.ban(ip) ──▶ iptables -A ... DROP
                                          │
                              écriture alert.jsonl (event_type=brute_force_ssh)


  [Thread de fond BanManager] ──(toutes les UNBAN_CHECK_INTERVAL_SEC)──▶
       vérifie les bans expirés ──▶ iptables -D ... ──▶ alert.jsonl (event_type=unban_ssh)
```

### Threading

Le processus principal exécute deux flux concurrents :

| Thread | Rôle | Fréquence |
|---|---|---|
| **Principal** | Lecture bloquante du log (`for line in proc.stdout`), détection, ban | dès qu'une ligne arrive |
| **`ban-unban-checker`** (daemon, dans `BanManager`) | Vérifie les bans expirés et les lève | toutes les `UNBAN_CHECK_INTERVAL_SEC` |

Les structures partagées (`banned_ips`, fichier d'état, fichier d'alertes) sont protégées par des verrous (`threading.Lock`) pour éviter les races conditions entre les deux threads.

---

## Prérequis

- Linux avec `iptables` installé et un service SSH (`sshd`) journalisant dans un fichier de log (ex. `/var/log/auth.log` sur Debian/Ubuntu, `/var/log/secure` sur RHEL/CentOS).
- Python ≥ 3.8 (utilisation de l'opérateur morse `:=`).
- Dépendances Python : `PyYAML` (chargement de la config), plus les modules internes du projet (aucune dépendance externe pour `ban_manager.py` / `rate_limiter.py`, qui n'utilisent que la stdlib).
- Droits `sudo`/`root` pour manipuler `iptables`.
- `systemd` pour un déploiement en tant que service (recommandé en prod).

---

## Installation

```bash
# 1. Cloner / copier l'arborescence du projet
sudo mkdir -p /opt/siem/{bin,lib,etc,var/state}
sudo cp bin/siem.py /opt/siem/bin/
sudo cp lib/*.py /opt/siem/lib/
sudo cp etc/config.yaml /opt/siem/etc/
sudo touch /opt/siem/lib/__init__.py

# 2. Vérifier les permissions (l'utilisateur qui lance le service doit
#    pouvoir lire le log SSH et écrire dans var/)
sudo chown -R mjb:mjb /opt/siem

# 3. Installer les dépendances Python
pip install --break-system-packages pyyaml

# 4. Vérifier que le script se lance sans erreur
sudo python3 /opt/siem/bin/siem.py --config /opt/siem/etc/config.yaml
```

---

## Configuration

Fichier YAML unique : `/opt/siem/etc/config.yaml`.

```yaml
# --- Détection ---
LOG_FILE: /var/log/auth.log
FAIL_PATTERNS:
  - 'Failed password.*from (\d{1,3}(?:\.\d{1,3}){3})'
  - 'Invalid user \S+ from (\d{1,3}(?:\.\d{1,3}){3})'
SEUIL: 5                        # nb de tentatives avant action
WINDOW_SEC: 300                  # fenêtre glissante (secondes)
WHITELIST:
  - 41.202.207.10                # IP jamais bannies

# --- Action ---
ACTION: block                    # "block" ou "alert" (dry-run)
USE_SUDO: true                    # préfixer les commandes iptables par sudo
COMMENT_TAG: SIEM-SSH-BRUTEFORCE

# --- Ban / Unban ---
BAN_DURATION_SEC: 1800             # durée du ban avant unban auto (30 min)
UNBAN_CHECK_INTERVAL_SEC: 30        # fréquence de vérif. des expirations
BAN_STATE_FILE: /opt/siem/var/state/banned_ips.json
RESET_BAN_ON_REPEAT: true            # prolonge le ban si l'IP retente pendant qu'elle est bannie

# --- Rate limiting des actions iptables ---
RATE_LIMIT_MAX_ACTIONS: 10
RATE_LIMIT_WINDOW_SEC: 60

# --- Sortie ---
OUTPUT_JSON: /opt/siem/var/alert.jsonl

# --- Test / debug ---
ALLOW_PRIVATE_IPS: false           # true pour tester depuis un réseau local
```

### Référence des clés

| Clé | Obligatoire | Défaut | Description |
|---|---|---|---|
| `LOG_FILE` | oui | — | Fichier surveillé via `tail -F` |
| `FAIL_PATTERNS` | non | `[]` | Liste de regex ; le 1er ou 2ᵉ groupe capturé est utilisé comme IP |
| `SEUIL` | oui | — | Nombre de tentatives déclenchant une action |
| `WINDOW_SEC` | non | `300` | Fenêtre glissante de comptage (s) |
| `ACTION` | oui | — | `block` (ban réel) ou toute autre valeur (dry-run) |
| `WHITELIST` | non | `[]` | IP jamais bannies |
| `OUTPUT_JSON` | oui | — | Chemin du journal d'alertes JSONL |
| `COMMENT_TAG` | oui | — | Préfixe du commentaire iptables (traçabilité) |
| `USE_SUDO` | non | `true` | Préfixe `sudo` sur les commandes iptables |
| `BAN_DURATION_SEC` | non | `1800` | Durée avant unban automatique |
| `UNBAN_CHECK_INTERVAL_SEC` | non | `30` | Intervalle du thread de vérification des unbans |
| `RATE_LIMIT_MAX_ACTIONS` | non | `10` | Nb max d'actions iptables par fenêtre |
| `RATE_LIMIT_WINDOW_SEC` | non | `60` | Fenêtre du rate limiter (s) |
| `BAN_STATE_FILE` | non | `/opt/siem/state/banned_ips.json` | Persistance des IP bannies |
| `RESET_BAN_ON_REPEAT` | non | `true` | Prolonge le ban plutôt que de l'ignorer si l'IP retente |
| `ALLOW_PRIVATE_IPS` | non | `false` | Désactive le filtre IP publique (tests locaux uniquement) |

---

## Déploiement en tant que service systemd

`/opt/siem/siem.service` :

```ini
[Unit]
Description=SIEM - Détection brute-force SSH (auth.log monitor)
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/siem
Environment=PYTHONPATH=/opt/siem
ExecStart=/usr/bin/python3 /opt/siem/bin/siem.py --config /opt/siem/etc/config.yaml
Restart=on-failure
RestartSec=5
User=mjb
# Si iptables nécessite root et que le service tourne sous un user non-root,
# configurer un sudoers NOPASSWD dédié (voir section Sécurité) ou passer User=root.

[Install]
WantedBy=multi-user.target
```

```bash
sudo cp siem.service /etc/systemd/system/siem.service
sudo systemctl daemon-reload
sudo systemctl enable --now siem.service
sudo systemctl status siem.service
```

### Commandes d'exploitation courantes

```bash
sudo systemctl status siem.service              # état du service
sudo systemctl restart siem.service               # redémarrage
sudo journalctl -u siem.service -n 50 --no-pager    # logs applicatifs récents
sudo journalctl -u siem.service -f                   # logs en direct
tail -f /opt/siem/var/alert.jsonl | jq .              # alertes en direct, formatées
cat /opt/siem/var/state/banned_ips.json | jq .         # IP actuellement bannies
sudo iptables -L INPUT -n --line-numbers | grep SIEM     # règles de ban actives
```

---

## Format des alertes (`alert.jsonl`)

Une ligne JSON par événement, deux types :

**Détection / ban** (`event_type: brute_force_ssh`) :
```json
{"timestamp": "2026-07-11T14:32:01.123456", "rule_id": "SIEM-SSH-BRUTEFORCE", "log_source": "/var/log/auth.log", "event_type": "brute_force_ssh", "src_ip": "203.0.113.42", "count": 5, "action": "DROP", "comment": "SIEM-SSH-BRUTEFORCE|..."}
```

**Unban** (`event_type: unban_ssh`) :
```json
{"timestamp": "2026-07-11T15:02:01.123456", "rule_id": "SIEM-SSH-BRUTEFORCE", "log_source": "/var/log/auth.log", "event_type": "unban_ssh", "src_ip": "203.0.113.42", "action": "UNBAN"}
```

### Valeurs possibles du champ `action`

| Valeur | Signification |
|---|---|
| `WATCH` | Tentative comptabilisée, seuil non atteint |
| `SAFE` | Seuil atteint mais IP en whitelist |
| `DRY_RUN` | Seuil atteint, `ACTION: alert` (pas de ban réel) |
| `DROP` | IP bannie avec succès |
| `FAIL_BAN` | Échec de la commande `iptables` au bannissement |
| `RATE_LIMITED` | Action reportée, limite du rate limiter atteinte |
| `ALREADY_BANNED` | IP déjà bannie, retente ignorée (`RESET_BAN_ON_REPEAT: false`) |
| `BAN_EXTENDED` | IP déjà bannie, ban prolongé (`RESET_BAN_ON_REPEAT: true`) |
| `UNBAN` | IP débannie avec succès (ban expiré) |
| `UNBAN_FAIL` | Échec de la commande `iptables` au débannissement |

---

## Utilisation / exploitation

### Vérifier qu'une IP est bien bannie

```bash
sudo iptables -L INPUT -n -v | grep <IP>
```

### Débannir manuellement une IP avant expiration

```bash
sudo iptables -D INPUT -s <IP> -j DROP -m comment --comment "<comment exact stocké dans banned_ips.json>"
```
Puis retirer l'entrée correspondante de `banned_ips.json` pour éviter une double tentative d'unban par le thread de fond.

### Ajouter une IP à la whitelist à chaud

Modifier `WHITELIST` dans `config.yaml`, puis `sudo systemctl restart siem.service` (pas de rechargement à chaud pour l'instant — voir Roadmap).

---

## Tests en local

Pour tester sans attendre une vraie attaque et sans que le filtre IP publique bloque les tentatives locales :

```yaml
ALLOW_PRIVATE_IPS: true
```

Puis générer des échecs de connexion :
```bash
for i in {1..6}; do ssh -o PreferredAuthentications=password mauvais_user@127.0.0.1; done
```

Vérifier en parallèle :
```bash
sudo tail -f /var/log/auth.log          # confirme que sshd journalise bien les échecs
sudo journalctl -u siem.service -f       # confirme que siem.py traite les lignes
tail -f /opt/siem/var/alert.jsonl         # confirme l'écriture des alertes
```

**Ne pas oublier de repasser `ALLOW_PRIVATE_IPS: false` avant la mise en prod.**

---

## Dépannage

| Symptôme | Piste |
|---|---|
| `ModuleNotFoundError` au démarrage | Vérifier `PYTHONPATH=/opt/siem` dans le `.service`, présence de `lib/__init__.py` |
| Service en `activating (auto-restart)` en boucle | `journalctl -u siem.service -n 50` pour voir la trace complète |
| `alert.jsonl` reste vide | Voir logs `[NO_MATCH]` / `[SKIP_PRIVATE]` (niveau DEBUG) — IP privée non autorisée, ou regex `FAIL_PATTERNS` qui ne matche pas le format réel des lignes |
| `PermissionError` sur `var/state/` ou `alert.jsonl` | Vérifier que le `User=` du service possède bien ces chemins |
| `[ERREUR IPTABLES:BAN]` | Le user du service n'a pas les droits `sudo` sans mot de passe sur `iptables` (voir Sécurité) |
| Ban jamais levé | Vérifier que le thread `ban-unban-checker` tourne (log `[THREAD] ban-unban-checker démarré`), et que `BAN_DURATION_SEC` / `UNBAN_CHECK_INTERVAL_SEC` sont cohérents |

---

## Sécurité

- **Principe du moindre privilège** : si le service tourne sous un user dédié (pas `root`), autoriser *uniquement* la commande `iptables` sans mot de passe via `/etc/sudoers.d/siem` :
  ```
  mjb ALL=(root) NOPASSWD: /usr/sbin/iptables
  ```
- **Ne pas bannir les IP de gestion** : toujours inclure l'IP d'administration légitime dans `WHITELIST` pour éviter de se couper l'accès SSH soi-même.
- **`BAN_STATE_FILE` et `alert.jsonl`** contiennent des IP sources d'attaque — traiter comme des données sensibles selon la politique de rétention de logs en vigueur.
- **Règles iptables non persistantes par défaut** : après un redémarrage de la machine (pas juste du service), les règles `DROP` actives disparaissent tant qu'un `iptables-persistent`/`netfilter-persistent` n'est pas configuré séparément. Le fichier d'état JSON garde la mémoire des bans côté application, mais ne réapplique pas automatiquement les règles iptables au boot.

---

## Limitations connues

- Pas de reconnexion automatique si le processus `tail -F` meurt de manière inattendue (le script se termine ; `Restart=on-failure` dans systemd relance tout le service).
- Pas de rechargement de configuration à chaud (nécessite un redémarrage du service).
- La persistance d'état ne revérifie pas la présence réelle des règles `iptables` au démarrage — en cas de désynchronisation manuelle (ex. `iptables -F`), `banned_ips.json` peut référencer des règles qui n'existent plus.

## Roadmap

- Reconnexion automatique du `tail` en cas de coupure du fichier de log.
- Rechargement de configuration à chaud (signal `SIGHUP`).
- Vérification de cohérence entre l'état persistant et les règles `iptables` réelles au démarrage.
- Support IPv6.
