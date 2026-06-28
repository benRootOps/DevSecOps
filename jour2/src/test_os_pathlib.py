import subprocess
import argparse
import logging
import os # <- 1. Pour créer dossier + taille fichier

RAPPORTS_DIR = "rapports" # Dossier constant

def setup():
    """Crée le dossier rapports/ s'il n'existe pas. Appelé au démarrage."""
    os.makedirs(RAPPORTS_DIR, exist_ok=True) #  Feature 1

def scan(args): # J'ai renommé arg en args. C'est la convention
    logging.info(f"Scan de {args.cible} démarré") #Ortho: démarré
    result = subprocess.run(
        ["nmap", "-p", args.port, "-sV", "-O", args.cible],
        capture_output=True, text=True
    )
    
    # Feature 2: Sauvegarde dans rapports/scan_<cible>.txt
    safe_cible = args.cible.replace(":", "_").replace("/", "_") # Évite les / dans le nom fichier
    filepath = os.path.join(RAPPORTS_DIR, f"scan_{safe_cible}.txt")
    with open(filepath, "w") as f:
        f.write(result.stdout)
    logging.info(f"Rapport sauvegardé dans {filepath}")

    if result.returncode != 0:
        logging.error(f"Erreur Nmap: {result.stderr}") #  Fix: f-string au lieu de ,
    else:
        logging.info("Terminé avec succès") # Ortho

def ping(args):
    logging.info(f"Ping de {args.cible} démarré")
    result = subprocess.run(
        ["ping", "-c", str(args.count), args.cible], # Fix: str() car argparse donne un str
        capture_output=True, text=True
    )
    logging.info(result.stdout)
    if result.returncode != 0:
        logging.error(f"Erreur Ping: {result.stderr}")
    else:
        logging.info("Terminé avec succès")

def info(args):
    logging.info(f"Affichage des infos du système") # Ortho

    result = subprocess.run(["whoami"], capture_output=True, text=True) 
    logging.info(result.stdout)
    if result.returncode != 0:
        logging.error(f"Erreur whoami: {result.stderr}")
    else:
        logging.info("whoami: Terminé avec succès")

    result = subprocess.run(["hostname"], capture_output=True, text=True)
    logging.info(result.stdout)
    if result.returncode != 0:
        logging.error(f"Erreur hostname: {result.stderr}")
    else:
        logging.info("hostname: Terminé avec succès")
    
    # Feature 3: Vérifier audit.log et taille
    log_path = "audit.log"
    if os.path.exists(log_path):
        taille = os.path.getsize(log_path)
        logging.info(f"Le fichier {log_path} existe. Taille: {taille} octets")
    else:
        logging.warning(f"Le fichier {log_path} n'existe pas encore.")

# --- CONFIG LOG ---
logging.basicConfig(
    level=logging.DEBUG,
    format="%(asctime)s - %(levelname)s - %(message)s",
    handlers=[logging.FileHandler("audit.log")]
)

# --- ARGPARSE ---
parser = argparse.ArgumentParser(description="Outil de sécurité DevSecOps Jour-01")
subparser = parser.add_subparsers(dest="commande") # <- Fix: subpaser -> subparser

scan_parser = subparser.add_parser("scan", help="Scanner avec Nmap")
scan_parser.add_argument("--cible", required=True, help="IP à scanner")
scan_parser.add_argument("--port", default="1-1000", help="Ports à scanner")
scan_parser.set_defaults(func=scan)

ping_parser = subparser.add_parser("ping", help="Ping d'adresse")
ping_parser.add_argument("--cible", required=True, help="IP à pinger")
ping_parser.add_argument("--count", default=3, type=int, help="Nombre de paquets") # <- Fix: type=int
ping_parser.set_defaults(func=ping)

info_parser = subparser.add_parser("info", help="Affiche les informations de la machine")
info_parser.set_defaults(func=info)

if __name__ == "__main__": #Bonne pratique
    setup() # Feature 1: On crée le dossier direct au lancement
    args = parser.parse_args()

    if args.commande is None:
        parser.print_help()
    else:
        args.func(args)