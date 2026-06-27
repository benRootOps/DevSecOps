import subprocess
import argparse


def scan(arg):
    result = subprocess.run(
        ["nmap", "-p", arg.port, "-sV", "-O", arg.cible],
        capture_output=True, text=True
    )
    print(result.stdout)
    if result.returncode != 0:
        print("Erreur 1 :", result.stderr)


def ping(arg):
    result = subprocess.run(
        ["ping", "-c", arg.count, arg.cible],
        capture_output=True, text=True
    )
    print(result.stdout)
    if result.returncode != 0:
        print("Erreur 2 :", result.stderr)

def info(arg):
    result=subprocess.run(["whoami"],
	 capture_output=True,text=True
    ) 
    print(result.stdout)
    if result.returncode != 0:
        print("Erreur 3 :", result.stderr)

    result=subprocess.run(["hostname"],
         capture_output=True,text=True
     )
    print(result.stdout)
    if result.returncode != 0:
        print("Erreur 3 :", result.stderr)

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

