import ipaddress
import logging, argparse, re, sys
from pathlib import Path
from collections import Counter

logging.basicConfig(level=logging.INFO, format="%(levelname)s - %(message)s")

parser = argparse.ArgumentParser(description="Extraction d'adresse IP depuis auth.log")
sub = parser.add_subparsers(dest="commande", required=True)

def check_file(file: Path) -> bool: # typo fix
    if not file.exists():
        logging.error(f"Le fichier : {file} n'existe pas")
        sys.exit(1)
    return True

def is_public(ip: str) -> bool:
    try:
        ip_obj = ipaddress.ip_address(ip)
        return not ip_obj.is_private and not ip_obj.is_loopback and not ip_obj.is_reserved
    except ValueError:
        return False # IP invalide = on ignore

def iplist(args):
    p = Path(args.path)
    check_file(p)

    # Regex IPv4 + IPv6. Prend aussi "invalid user"
    regex = r'Failed password for (?:invalid user \S+|\S+) from ([\da-fA-F\.:]+)'

    counts = Counter()
    with p.open('r', errors='ignore') as f: # Stream ligne par ligne = pas de crash RAM
        for line in f:
            match = re.search(regex, line)
            if match:
                ip = match.group(1)
                if is_public(ip): # On ne compte que le public
                    counts[ip] += 1

    if not counts:
        logging.warning("Aucune tentative publique trouvée.")
        return

    logging.info("--- Top 5 Attaquants Publics ---")
    for ip, count in counts.most_common(5):
        logging.info(f"{ip}: {count} tentatives")

    logging.info(f"\nIP publiques uniques trouvées [{len(counts)}] :")
    for ip in sorted(counts):
        logging.info(ip)
    logging.info(f"Scan terminé. Total lignes lues: {sum(counts.values())}")

ip_list = sub.add_parser("iplist", help="Liste les IP en échec SSH")
ip_list.add_argument("--path", required=True, help="Chemin du fichier log, ex: /var/log/auth.log")
ip_list.set_defaults(func=iplist)

if __name__ == "__main__":
    args = parser.parse_args()
    args.func(args)