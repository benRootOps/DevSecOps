import argparse, re, ipaddress, subprocess
import logging
from pathlib import Path
from collections import Counter
from comment_builder import iptables_comment

SEUIL = 5

def is_public(ip):
    try:
        obj_ip = ipaddress.ip_address(ip)
        
        # HACK SOC: Ça marche en method() ET en property
        is_priv = obj_ip.is_private if callable(obj_ip.is_private) else obj_ip.is_private
        is_loop = obj_ip.is_loopback if callable(obj_ip.is_loopback) else obj_ip.is_loopback
        is_resv = obj_ip.is_reserved if callable(obj_ip.is_reserved) else obj_ip.is_reserved

        return not is_priv and not is_loop and not is_resv
    except ValueError:
        return False

def main(args):
    p = Path(args.auth)
    compteur = Counter()
    block_list = set()
    regex = r'Failed password for (?:invalid user \S+|\S+) from ([\da-fA-F\.:]+)'

    with p.open('r') as f:
        for line in f:
            match = re.search(regex, line)

            if match and is_public(match.group(1)): # 1. CHECK MATCH D’ABORD
                ip = match.group(1) # 2. PRENDS GROUP(1) APRES

                compteur[ip] += 1
                comment = iptables_comment("J7", args.auth, compteur[ip]) # <- renommé

                if compteur[ip] > SEUIL and ip not in block_list:
                    block_list.add(ip)
                    cmd = ["sudo", "iptables", "-A", "INPUT", "-s", ip, "-j", "DROP", "-m", "comment", "--comment", comment]
                    logging.warning(f"[ALERTE] {ip} -> BAN | {comment}")
                    # subprocess.run(cmd, check=True) # <- Laisse commenté pour DRY-RUN

    logging.info("\n--- DASHBOARD TOP 5 ---")
    for ip, count in compteur.most_common(5):
        status = "BANNED" if ip in block_list else "WATCH"
        print(f"{ip:<20} | {count:>4} | {status}")

if __name__ == "__main__":
    logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")
    parser = argparse.ArgumentParser(description="SIEM COMPLET PROD")
    parser.add_argument("--auth", required=True, help="Chemin vers auth.log") # <- required=True sinon args.auth = None
    args = parser.parse_args()
    main(args)