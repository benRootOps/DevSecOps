from datetime import datetime
from pathlib import Path

def iptables_comment(rule_id:str, src_file:str,count:int):

    fichier_actif = Path(src_file).name
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M") # ->

    comment = f"{rule_id} | SRC:{fichier_actif} | TS:{timestamp} | CNT:{count}"
    return comment[:256]