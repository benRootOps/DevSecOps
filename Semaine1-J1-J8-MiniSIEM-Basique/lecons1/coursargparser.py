import argparse


parse= argparse.ArgumentParser(description="Mon premier outils CLI")

parse.add_argument("--cible", required=True, help="IP cible")

parse.add_argument("--count", default="3", help="nombre ping par defaut")

args=parse.parse_args()

print("Cible :", args.cible)
print("Count : ", args.count)