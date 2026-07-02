import argparse
from pathlib import Path
import ipaddress, re
from collections import Counter # Pour compter

def is_public(ip: str) -> bool:
    try:
        ip_obj = ipaddress.ip_address(ip)
        return not ip_obj.is_private and not ip_obj.is_loopback and not ip_obj.is_reserved
    except ValueError:
        return False

def get_ssh_attackers(path: Path) -> set: # Renvoie un SET
    regex = r'Failed password for (?:invalid user \S+|\S+) from ([\da-fA-F\.:]+)'
    attackers = set() # Set = pas de doublon
    with path.open('r') as f:
        for line in f:
            match = re.search(regex, line)
            if match and is_public(match.group(1)):
                attackers.add(match.group(1)) # .add() pour set
    return attackers

def get_fw_scanners(path: Path) -> set: # Renvoie un SET
    regex_src = r'SRC=([\da-fA-F\.:]+)'
    scanners = set()
    with path.open('r') as f:
        for line in f:
            if "UFW BLOCK" in line:
                match = re.search(regex_src, line)
                if match and is_public(match.group(1)):
                    scanners.add(match.group(1))
    return scanners

def main(args):
    ssh_ips = get_ssh_attackers(Path(args.auth))
    fw_ips = get_fw_scanners(Path(args.ufw))
    
    confirme = ssh_ips & fw_ips # MAGIE: Intersection des 2 sets
    
    print("--- Corrélation Terminée ---")
    if not confirme:
        print("Aucun attaquant confirmé.")
    for ip in confirme:
        print(f"{ip}: Présent dans SSH + Firewall. Menace Confirmée.")
    
    print("\n--- Règles PRIORITAIRE iptables ---")
    for ip in confirme:
        print(f"iptables -A INPUT -s {ip} -j DROP -m comment --comment \"SIEM CORRELATION\"")
        

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Mini SIEM")
    parser.add_argument("--auth", required=True, help="Chemin du fichier auth.log")
    parser.add_argument("--ufw", required=True, help="Chemin du fichier ufw.log")
    parser.add_argument("--top", type=int, default=5, help="Nombre d'ip à afficher")
    args = parser.parse_args()
    main(args) # On appelle main(args)