import subprocess
import argparse


parser=argparse.ArgumentParser(description="Mon programme CLI")

parser.add_argument("--ip", help="ip à ping")
parser.add_argument("--count", default="4", help="nombre d'occurence")

args=parser.parse_args()

print("Debut du ping...")
result=subprocess.run(["ping","-c",args.count,args.ip], capture_output=True,text=True)

print(result.stdout)
print(result.stderr)

if result.returncode==0:
    print("Ok")
else:
    print("FAILED")

    

