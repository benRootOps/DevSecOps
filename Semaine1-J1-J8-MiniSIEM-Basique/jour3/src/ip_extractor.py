import re,logging,argparse,subprocess,sys
from pathlib import Path

# def filecheck(args):
#     p = Path(args.path) 
    
#     if not p.exists():
#         logging.error(f"Fichier {p} introuvable")
#         sys.exit(1)
    
#     size = p.stat().st_size 
#     logging.info(f"Taille: {size} bytes")
#     p.read_text().splitlines()[-5:]
    

# def main():
#   
#     p = sub.add_parser("filecheck")
#     p.add_argument("--path", type=str, required=True)
#     p.set_defaults(func=filecheck)
import ipaddress

logging.basicConfig(level=logging.INFO, format="%(levelname)s - %(message)s")

parser=argparse.ArgumentParser(description="Extraction d'adresse ip")
sub=parser.add_subparsers(dest="commande", required=True)

def checfile(file: Path) -> bool:
    if not file.exists():
        logging.error(f"Le fichier : {file} n'existe pas")
        sys.exit(1)
    return True
       
def is_private(ip: str) -> bool:
   
    try:
        return ipaddress.ip_address(ip).is_private
    except ValueError:
        return False # IP invalide = on la considère publique/filtrable

def iplist(args):
    p=Path(args.path)
    checfile(p)
    
    text = p.read_text()
    match = re.findall(r'Failed password for.* from (\d+\.\d+\.\d+\.\d+)', text)
    ip_unique = set(match) 

   
    public_ips = sorted(ip for ip in ip_unique if not is_private(ip))
    

    logging.info(f"IP publiques trouvées [{len(public_ips)}] :")
    for ip in public_ips:
        logging.info(ip)

ip_list = sub.add_parser("iplist")
ip_list.add_argument("--path", required=True, help="Chemin du fichier log")
ip_list.set_defaults(func=iplist)

if __name__ == "__main__":
     args = parser.parse_args()
     args.func(args)