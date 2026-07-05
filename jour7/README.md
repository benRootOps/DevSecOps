### _`README.md` = Mini SIEM J1 à J7_

# jour7

`Fail2Ban` maison en Python | 7 Jours de SOC Engineering

Un SIEM léger qui parse `auth.log`, détecte le brute-force SSH, et ban via `iptables` avec audit trail complet.

**Auteur**: [mjbGEEKPRO] | **Stack**: Python 3.10+ | **Status**: J7 Prod Ready

---

### **ROADMAP 7 JOURS | J1 -> J7**

| **Jour** | **Compétence SOC** | **Livrable Code**         | **Concept L2**       |
| -------- | ------------------ | ------------------------- | -------------------- |
| **J1**   | `grep` Python      | `re.search()` basique     | Lire `auth.log`      |
| **J2**   | Filtre RFC1918     | `ipaddress.is_private()`  | 0 Faux Positif LAN   |
| **J3**   | Comptage           | `collections.Counter()`   | Stateful Detection   |
| **J4**   | Seuil & Action     | `if count > SEUIL`        | Logique Fail2Ban     |
| **J5**   | Anti-Doublon       | `set()` block_list        | 1 IP = 1 Ban         |
| **J6**   | Audit Trail        | `iptables -m comment`     | Traçabilité Auditeur |
| **J7**   | Architecture Pro   | 2 Fichiers + `callable()` | Code Portable GitHub |

---

### **ARCHITECTURE J7 PROD**

jour7/
├── siem_final.py # Moteur SOC: Parse > Detect > Action
├── comment_builder.py # Lib Audit: Format --comment iptables
├── http://auth.log # Fichier de test
└── http://README.md # Documentation

### **FONCTIONNALITÉS CLÉS J7**

1.  **Portable 3.10+**: Gère `property` Ubuntu vs `method` Python.org via `callable()`.
2.  **0 Hardcode Audit**: Le `comment` est build dans `comment_builder.py`. DRY.
3.  **DRY-RUN Safe**: `subprocess.run()` commenté par défaut. 0 ban accidentel.
4.  **Dashboard CLI**: `TOP 5` des IPs avec statut `WATCH` / `BANNED`.
5.  **Seuil Configurable**: `SEUIL = 5` modifiable en 1 ligne.

### **INSTALL & USAGE**

```bash
# 1. Clone
git clone https://github.com/mjbGEEKPRO/jour7.git
cd jour7/src

# 2. DRY-RUN Test - Obligatoire
sudo python3 siem_final.py --auth logs.txt
# -> Vérifie [ALERTE] et le DASHBOARD

# 3. LIVE FIRE - Attention
# Décommente `subprocess.run(cmd, check=True)` dans siem_final.py
sudo python3 siem_final.py --auth /var/log/auth.log

# 4. Vérif Règle
sudo iptables -S | grep J7
### *EXEMPLE DE SORTIE*
2025-10-04 12:03:11 - WARNING - [ALERTE] 8.8.8.8 -> BAN | J7|SRC:logs.txt|CNT:6|TS:2025-10-04 12:03
2025-10-04 12:03:11 - INFO -
--- DASHBOARD TOP 5 ---
8.8.8.8              |    6 | BANNED
192.168.1.10         |    2 | WATCH
### *NEXT STEPS | J8 TEASER*
- `config.yaml` pour externaliser `SEUIL`, `log_file`, `action`
- `argparse` avancé: `--dry-run`, `--threshold 10`
- Export `JSON` pour ingestion dans Elasticsearch/Wazuh

---
*Disclaimer SOC*: Usage Lab/Educatif uniquement. Testez sur VM. Je ne suis pas responsable d’un ban de prod.

```
