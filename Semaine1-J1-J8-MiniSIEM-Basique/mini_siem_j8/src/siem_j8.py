import argparse, re, ipaddress, subprocess, yaml 
import logging
from pathlib import Path
from collections import Counter
from comment_builder import iptables_comment

def is_public(ip: str) -> bool:
    """Compatible Ubuntu property + Python method"""
    try:
        obj_ip = ipaddress.ip_address(ip)
        is_priv = obj_ip.is_private() if callable(obj_ip.is_private) else obj_ip.is_private
        is_loop = obj_ip.is_loopback() if callable(obj_ip.is_loopback) else obj_ip.is_loopback
        is_resv = obj_ip.is_reserved() if callable(obj_ip.is_reserved) else obj_ip.is_reserved
        return not is_priv and not is_loop and not is_resv
    except ValueError:
        return False

def main(config):
    SEUIL = config['seuil']
    LOG_FILE = config['log_file']
    TAG = config['comment_tag']
    ACTION = config['action']

    p = Path(LOG_FILE)
    compteur = Counter()
    block_list = set()
    regex = r'Failed password for (?:invalid user \S+|\S+) from ([\da-fA-F\.:]+)'

    logging.info(f"[CONFIG] SEUIL={SEUIL} | FILE={LOG_FILE} | MODE={ACTION}")

    with p.open('r') as f:
        for line in f:
            if match := re.search(regex, line):
                ip = match.group(1)
                if is_public(ip):
                    compteur[ip] += 1
                    comment = iptables_comment(TAG, LOG_FILE, compteur[ip])

                    if compteur[ip] > SEUIL and ip not in block_list:
                        block_list.add(ip)
                        cmd = ["sudo", "iptables", "-A", "INPUT", "-s", ip, "-j", "DROP", "-m", "comment", "--comment", comment]
                        logging.warning(f"{ip} -> BAN | {comment}")

                        if ACTION == "live":
                            subprocess.run(cmd, check=True)
                        else:
                            logging.info(f"[DRY-RUN] Commande: {' '.join(cmd)}")

    logging.info("\n--- DASHBOARD TOP 5 ---")
    for ip, count in compteur.most_common(5):
        status = "BANNED" if ip in block_list else "WATCH"
        print(f"{ip:<20} | {count:>4} | {status}")

if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

    parser = argparse.ArgumentParser(description="SIEM J8 Configurable")
    parser.add_argument("--config", required=True, help="Chemin vers config.yaml")
    args = parser.parse_args()

    with open(args.config, 'r') as f:
        config = yaml.safe_load(f) #  Charge le YAML

    main(config)