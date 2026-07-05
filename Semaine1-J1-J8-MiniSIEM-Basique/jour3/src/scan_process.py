import argparse, subprocess, sys, logging, re
from pathlib import Path 

logging.basicConfig(level=logging.INFO, format='%(levelname)s: %(message)s')

def get_pid(port: int) -> int | None:
    cmd = ['ss', '-tulnp']
    result = subprocess.run(cmd, capture_output=True, text=True, check=True)
    # Regex pour choper pid=1234 dans users:(("nc",pid=1234,fd=3))
    match = re.search(rf':{port}.*pid=(\d+)', result.stdout)
    return int(match.group(1)) if match else None

def pid(args):
    pid_val = get_pid(args.port)
    if not pid_val:
        logging.error(f"Aucun process sur le port {args.port}")
        sys.exit(1)
    
    ps = subprocess.run(['ps', '-p', str(pid_val), '-o', 'user,cmd'], capture_output=True, text=True)
    logging.info(f"Port {args.port} -> PID {pid_val}")
    logging.info(ps.stdout)

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    sub = parser.add_subparsers(dest="cmd", required=True)
    p = sub.add_parser("pid")
    p.add_argument("--port", type=int, choices=range(1, 65536), required=True)
    p.set_defaults(func=pid)
    args = parser.parse_args()
    args.func(args)