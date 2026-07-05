### _`README.md` = Mini SIEM v1.0 | Semaine 1_

# Mini-SIEM v1.0

`Fail2Ban` maison en Python | SOC Engineering Week 1

Un SIEM léger, configurable et portable qui parse `auth.log`, détecte le brute-force SSH > SEUIL, et ban via `iptables` avec audit trail.

**Stack**: Python 3.10+ | `PyYAML` | `ipaddress` | `argparse` | `subprocess`
**Status**: `v1.0 Prod-Ready` | **Mode**: `DRY-RUN / LIVE`

---

### **FONCTIONNALITÉS CLÉS V1.0**

| **Module**       | **Techno SOC**                     | **Valeur Prod**                                   |
| ---------------- | ---------------------------------- | ------------------------------------------------- | ---- | ---- | ---- |
| **1. Detection** | `re` + `collections.Counter`       | Stateful Brute-Force Detection                    |
| **2. Filtrage**  | `ipaddress` + `callable()` Hack    | 0 Faux Positif RFC1918. Code Portable Ubuntu/Arch |
| **3. Action**    | `subprocess` + `iptables -j DROP`  | Auto-Ban avec traçabilité                         |
| **4. Audit**     | `iptables -m comment` + Lib dédiée | Traçabilité Auditeur: `J8                         | SRC: | CNT: | TS:` |
| **5. Config**    | `config.yaml` + `argparse`         | 0 Hardcode. Change `seuil` sans redéployer        |
| **6. Safety**    | `DRY-RUN` vs `LIVE` Mode           | Test en Lab avant Prod. 0 Ban Accidentel          |

---

### **ARCHITECTURE DU PROJET**

Semaine1-J1-J8-MiniSIEM-Basique/
├── siem_j8.py # Moteur SOC: Parse > Detect > Action > Config
├── comment_builder.py # Lib: Formatage du --comment iptables V256
├── http://config.yaml # Fichier de config externe. Seul point d’entrée Ops
└── http://auth.log # Fichier de test pour DRY-RUN

### **INSTALL & USAGE PROD**

```bash
# 1. Dépendances
pip install pyyaml

# 2. Configurer
nano config.yaml
# seuil: 5
# log_file: "/var/log/auth.log"
# action: "dry-run" # Mettez "live" après test

# 3. DRY-RUN OBLIGATOIRE
sudo python3 siem_j8.py --config config.yaml
# -> Vérifiez [DRY-RUN] Commande: sudo iptables...

# 4. LIVE FIRE
# action: "live" dans config.yaml
sudo python3 siem_j8.py --config config.yaml

# 5. Vérification Règle
sudo iptables -S | grep J8
### *EXEMPLE DE SORTIE DRY-RUN*
2025-10-04 12:03:11 - INFO - [CONFIG] SEUIL=5 | FILE=logs.txt | MODE=dry-run
2025-10-04 12:03:11 - WARNING - [ALERTE] 8.8.8.8 -> BAN | J8|SRC:logs.txt|CNT:6|TS:2025-10-04 12:03
2025-10-04 12:03:11 - INFO - [DRY-RUN] Commande: sudo iptables -A INPUT -s 8.8.8.8 -j DROP -m comment --comment J8|SRC:logs.txt|CNT:6|TS:2025-10-04 12:03

--- DASHBOARD TOP 5 ---
8.8.8.8              |    6 | BANNED
### *ROADMAP SEMAINE 1 : J1 -> J8*
`Parse Log` > `Filtre RFC1918` > `Stateful Counter` > `Seuil` > `Anti-Doublon` > `Audit Comment` > `Code Portable` > `Config YAML + DRY-RUN`

### *NEXT STEPS | SEMAINE 2 TEASER*
- `Export JSON` pour ingestion Wazuh/Splunk/ELK
- `Systemd Service` pour run en daemon 24/7
- `Whitelist` IP internes via `config.yaml`

---
*Disclaimer SOC*: Usage Lab/Educatif. Testez sur VM. L’auteur n’est pas responsable d’un ban de prod. `action: live` = Risque.

---
```
