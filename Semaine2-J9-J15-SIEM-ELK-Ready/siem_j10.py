import re
import subprocess
import json
from collections import Counter
from pathlib import Path
from siem_core import load_config, setup_logging
from ip_public import is_public
from datetime import datetime
from comment_builder import iptables_comment

def main():
    config = load_config() # Charge tout le yaml
    logger = setup_logging()

    compteur = Counter()
    block_list = set() # Pour ne ban qu'une fois par run

    regex = r'Failed password for (?:invalid user \S+|\S+) from ([\da-fA-F\.:]+)'

    # 1. LIT LE YAML CORRECTEMENT
    SEUIL = config['SEUIL']
    LOG_FILE = config['LOG_FILE']
    ACTION = config['ACTION'] # alert | block
    WHITELIST = config.get('WHITELIST', []) # [] si pas défini
    JSON_OUT = Path(config['OUTPUT_JSON'])
    TAG = config['COMMENT_TAG']

    logger.info(f"[CONFIG] SEUIL={SEUIL} | ACTION={ACTION} | WHITELIST={WHITELIST}")

    with Path(LOG_FILE).open('r') as f:
        for line in f:
            if match := re.search(regex, line):
                ip = match.group(1)

                if is_public(ip):
                    compteur[ip] += 1
                    count = compteur[ip]
                    comment = iptables_comment(TAG, LOG_FILE, count)

                    # 2 LOGIQUE J11: SEUIL + WHITELIST + ACTION
                    if count > SEUIL and ip not in block_list:

                        # CHECK WHITELIST D'ABORD
                        if ip in WHITELIST:
                            logger.warning(f" [SAFE] {ip} est en WHITELIST. Pas de ban. Count={count}")
                            continue # On skip le ban

                        block_list.add(ip) # Lock pour ne pas re-ban
                        ts_iso = datetime.now().isoformat()

                        cmd = ["sudo", "iptables", "-A", "INPUT", "-s", ip, "-j", "DROP", "-m", "comment", "--comment", comment]

                        # ACTION
                        if ACTION == "block": #pour l'utiliser changer "alert" par "block" dans le config.yaml
                            logger.critical(f"[BLOCAGE] {ip} -> {count} fails. Execution: {' '.join(cmd)}")
                            try:
                                subprocess.run(cmd, check=True)
                                action_taken = "DROP"
                            except Exception as e:
                                logger.error(f"[ERREUR IPTABLES] {e}")
                                action_taken = "FAIL_BAN"
                        else: # ACTION == "alert"
                            logger.warning(f"[ALERTE] {ip} -> {count} fails | DRY-RUN: {' '.join(cmd)}")
                            action_taken = "DRY_RUN"

                        # 3. EXPORT JSONL POUR SIEM TOUJOURS
                        alert = {
                            "timestamp": ts_iso,
                            "rule_id": TAG,
                            "event_type": "brute_force_ssh",
                            "src_ip": ip,
                            "count": count,
                            "action": action_taken,
                            "comment": comment,
                            "log_source": LOG_FILE
                        }
                        with JSON_OUT.open('a') as jsonf:
                            jsonf.write(json.dumps(alert) + "\n")

    # 4. DASHBOARD FINAL
    logger.info("\n--- DASHBOARD TOP 5 ---")
    for ip, count in compteur.most_common(5):
        status = "BANNED" if ip in block_list else "WATCH"
        print(f"{ip:<20} | {count:>4} | {status}")

if __name__ == "__main__":
    # ASSUREZ VOUS TOUJOURS D'AVOIR DEFINIS VOTRE IP EN WITHLIST DANS LE config.yaml POUR EVITER DES DESAGREMENTS
    # SI VOUS AVEZ VERROUILLEZ VOTRE IP MAIS QUE VOUS AVEZ ACCES AU SERVEUR FAITE : 
    # sudo iptables -D INPUT -s votre_ip - DROP
    main()