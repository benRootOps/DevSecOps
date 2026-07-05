import argparse
from pathlib import Path
import subprocess

# parser=argparse.ArgumentParser(description="Verifier un logs.txt")
# parser.add_argument("--file",required=True,help="Non du fichier")

# args=parser.parse_args()

# p=Path.cwd()

# chemin=p/args.file
# if chemin.is_file():
#     contenu=chemin.read_text(encoding="latin-1")
#     print(contenu)
# else:
#     print("le fichier n'existe pas")


#Exercice 2


# parser=argparse.ArgumentParser(description="Affichage fichier")

# parser.add_argument("--cmd",help="afficher le contenue du dossier")
# args=parser.parse_args()
# result=subprocess.run([args.cmd],shell=True, capture_output=True,text=True)

# print("::::::::::::::Contenue du dossier::::::::::::::")

# print(result.stdout)
# print(result.stderr)

