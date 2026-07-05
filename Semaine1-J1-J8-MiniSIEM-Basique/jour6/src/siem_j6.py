import argparse
from pathlib import Path
import ipaddress, re,logging
from collections import Counter


logging.basicConfig(level=logging.INFO,format=" %(asctime)s - %(levelname)s - %(message)s")

SEUIL=5

def is_public(ip):
    try:
        obj_ip = ipaddress.ip_address(ip)
        return not obj_ip.is_private and not obj_ip.is_loopback and not obj_ip.is_reserved
    except ValueError:
        return False

def main(args):
    compteur = Counter()
    block_list = set()
    regex = r'Failed password for (?:invalid user \S+|\S+) from ([\da-fA-F\.:]+)'
    p = Path(args.auth)

    with p.open('r') as f:
        for line in f:
            match = re.search(regex, line)
            if match and is_public(match.group(1)):
                ip = match.group(1)
                compteur[ip] += 1

                # 2. On check le seuil DIRECTEMENT ICI, pas après
                if compteur[ip] > SEUIL and ip not in block_list:
                    block_list.add(ip)
                    logging.info(f"iptables -A INPUT -s {ip} -j DROP -m comment --comment \"J6 FAIL2BAN\"")
                    logging.info("IP BLOQUER")

    print("\n--- TOP 5 Attaquants ---")
    for ip, count in compteur.most_common(5):
        logging.info(f"compteur {ip} {count}")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Fail2Ban Mini SIEM")
    parser.add_argument("--auth", required=True, help="Chemin du fichier auth.log")
    args = parser.parse_args()
    main(args)