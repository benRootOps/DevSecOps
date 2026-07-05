import re
import ipaddress
import logging
from pathlib import Path
from collections import Counter


logging.basicConfig(
    level=logging.DEBUG,
    format="%(asctime)s - %(levelname)s - %(message)s",
)


def is_public(ip: str) -> bool:
    try:
        ip_obj = ipaddress.ip_address(ip)
        return not ip_obj.is_private and not ip_obj.is_loopback
    except ValueError:
        return False

p = Path("ufw.log")
ports_cibles = {"22", "80", "3389"}
regex_src = r'SRC=([\da-fA-F\.:]+)'
regex_dpt = r'DPT=(\d+)'

counts = Counter()

with p.open('r') as f:
    for line in f:
        if "UFW BLOCK" in line:
            ip_match = re.search(regex_src, line)
            dpt_match = re.search(regex_dpt, line)
            if ip_match and dpt_match:
                ip, port = ip_match.group(1), dpt_match.group(1)
                if port in ports_cibles and is_public(ip): # Filtre port + IP publique
                    counts[ip] += 1

logging.info("--- Top Scanners ---")
for ip, count in counts.most_common(10):
    logging.info(f"{ip}: {count} tentatives")

logging.info("\n--- Règles iptables à appliquer ---")
for ip in counts:
    logging.info(f"iptables -A INPUT -s {ip} -j DROP")