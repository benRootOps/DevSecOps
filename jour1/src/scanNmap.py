import subprocess
import argparse



parser=argparse.ArgumentParser(description="scan nmap")

parser.add_argument("--cible",required=True,help="IP à scanner")
parser.add_argument("--port",default="1-1000")

arg=parser.parse_args()


print("Début du scan")
result=subprocess.run(["nmap","-p",arg.port,"-sV","-O",arg.cible], capture_output=True,text=True)

print(result.stdout)

if result.returncode==0:
	print("OK")
else:
	print("Failed")
	print("Erreur : ",result.stderr)


