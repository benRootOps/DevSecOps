import subprocess
import argparse


#creation du parser
parser=argparse.ArgumentParser(description="ping CLI")

#ajout des argument
parser.add_argument("--ip",required=True,help="IP à ping")
arg=parser.parse_args()

print("Debut du ping")

result=subprocess.run(["ping","-c","4",arg.ip],capture_output=True,text=True)

print(result.stdout)
print(result.stderr)
