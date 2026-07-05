# # import logging

# # logging.debug("Détail technique")      # niveau 1 — très verbeux
# # logging.info("Action normale")         # niveau 2 — informatif
# # logging.warning("Quelque chose cloche")# niveau 3 — attention
# # logging.error("Quelque chose a raté")  # niveau 4 — erreur
# # logging.critical("Système en danger")  # niveau 5 — critique

# import logging



# # logging.basicConfig(
# #     level=logging.DEBUG, #on affcihe les resultats appartir de Debug
# #     format="%(asctime)s - %(levelname)s - %(message)s"
# # )

# # logging.info("scan démarrer")
# # logging.warning("Port 22 ouvert détecter")
# # logging.error("Nmap introuvable")

# logging.basicConfig(
#     level=logging.DEBUG,
#     format="%(asctime)s - %(levelname)s - %(message)s ",
#     handlers=[
#         logging.StreamHandler(), #afficher dans le terminal
#         logging.FileHandler("audit.log") # sauvegarder dans un fichier
#     ]
# )

# logging.info("Outil démarrer")
# logging.warning("Cible non jouagnable")

import subprocess
import argparse
import logging
import os
from pathlib import Path

#configuration du logging

p=Path("rapports")

p.mkdir(parents=True, exist_ok=True)

logging.basicConfig(
    level=logging.DEBUG,
    format="%(asctime)s - %(levelname)s - %(message)s",
    handlers=[
        #on ne vas pas afficher dans le terminal
        logging.FileHandler("audit.log")
    ]
)



def scan(arg):
    logging.info(f"Scan de {arg.cible} démarrer")
    result = subprocess.run(
        ["nmap", "-p", arg.port, "-sV", "-O", arg.cible],
        capture_output=True, text=True
    )
    logging.info(result.stdout)
    if result.returncode != 0:
        logging.error("Erreur 1 :", result.stderr)
    else:
        logging.info("Terminer avec succès")


def ping(arg):
    logging.info(f"Ping de {arg.cible} démarrer")

    result = subprocess.run(
        ["ping", "-c", arg.count, arg.cible],
        capture_output=True, text=True
    )
    logging.info(result.stdout)
    if result.returncode != 0:
        logging.error("Erreur 2 :", result.stderr)
    else:
        logging.info("Terminer avec succès")


def info(arg):
    logging.info(f"Affichage des info du system")

    result=subprocess.run(["whoami"],
	 capture_output=True,text=True
    ) 
    logging.info(result.stdout)
    if result.returncode != 0:
        logging.error("Erreur 3 :", result.stderr)
    else:
        logging.info("Terminer avec succès")


    result=subprocess.run(["hostname"],
         capture_output=True,text=True
     )
    logging.info(result.stdout)
    if result.returncode != 0:
        logging.error("Erreur 3 :", result.stderr)
    else:
        logging.info("Terminer avec succès")



parser = argparse.ArgumentParser(description="Outil de sécurité")
subpaser = parser.add_subparsers(dest="commande")

scan_parser = subpaser.add_parser("scan", help="Scanner avec Nmap")
scan_parser.add_argument("--cible", required=True, help="IP à scanner")
scan_parser.add_argument("--port", default="1-1000")
scan_parser.set_defaults(func=scan)  # lie la commande à la fonction

ping_parser = subpaser.add_parser("ping", help="Ping d'adresse")
ping_parser.add_argument("--cible", required=True, help="IP à pinger")
ping_parser.add_argument("--count", default="3", help="Nombre de paquets")
ping_parser.set_defaults(func=ping)  # lie la commande à la fonction

info_parser=subpaser.add_parser("info",help="Affichez les information de la machine")
info_parser.set_defaults(func=info)
arg = parser.parse_args()

if arg.commande is None:
    parser.print_help()
else:
    arg.func(arg)

