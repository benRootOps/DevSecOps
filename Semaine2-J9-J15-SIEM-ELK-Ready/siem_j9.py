# siem_j9.py
import re, ipaddress, subprocess, json
from pathlib import Path
from collections import Counter
from datetime import datetime
from comment_builder import iptables_comment
from siem_core import load_config, setup_logging

def is_public(ip: str) -> bool:
    try:
        obj_ip = ipaddress.ip_address(ip)
        return not obj_ip.is_private and not obj_ip.is_loopback and not obj_ip.is_reserved
    except ValueError:
        return False

def main():
    config = load_config() 
    logger = setup_logging() 

    SEUIL, LOG_FILE, TAG, ACTION, JSON_OUT = config['SEUIL'], config['LOG_FILE'], config['COMMENT_TAG'], config['ACTION'], Path(config['OUTPUT_JSON'])
    logger.info(f"[CONFIG] SEUIL={SEUIL} | MODE={ACTION} | JSON={JSON_OUT}")
    
    p=Path(LOG_FILE)
    compteur=Counter()
    block_list=set()
    regex = r'Failed password for (?:invalid user \S+|\S+) from ([\da-fA-F\.:]+)'

    with p.open('r') as f:
        for line in f:
            if match := re.search(regex,line):
                ip=match.group(1)
                if is_public(ip):
                    compteur[ip] += 1
                    count = compteur[ip]
                    comment = iptables_comment(TAG, LOG_FILE, compteur[ip])

                    if count > SEUIL and ip not in block_list:
                        block_list.add(ip)
                        ts_iso = datetime.now().isoformat()

                        # ACTION IPTABLES
                        cmd = ["sudo", "iptables", "-A", "INPUT", "-s", ip, "-j", "DROP", "-m", "comment", "--comment", comment]
                        logger.warning(f"[ALERTE] {ip} -> BAN | {comment}")
                        if ACTION == "live":
                            subprocess.run(cmd, check=True)
                        else:
                            logger.info(f"[DRY-RUN] Commande: {' '.join(cmd)}")

                        # 2. EXPORT JSONL POUR SIEM
                        alert = {
                            "timestamp": ts_iso,
                            "rule_id": TAG,
                            "event_type": "brute_force_ssh",
                            "src_ip": ip,
                            "count": count,  
                            "action": "DROP" if ACTION == "live" else "DRY_RUN",
                            "comment": comment,
                            "log_source": LOG_FILE
                        }
                        with JSON_OUT.open('a') as jsonf:
                            jsonf.write(json.dumps(alert) + "\n")


    logger.info("\n--- DASHBOARD TOP 5 ---")
    for ip, count in compteur.most_common(5):
        status = "BANNED" if ip in block_list else "WATCH"
        print(f"{ip:<20} | {count:>4} | {status}")


if __name__ == "__main__":
    main()