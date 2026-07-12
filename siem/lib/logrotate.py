import os
from pathlib import Path
from datetime import datetime

MAX_SIZE_MB = 10 # Taille par défaut. On peut override

def rotate_if_full(path: str, backup_dir: str, max_mb: int = MAX_SIZE_MB):
    if not os.path.exists(path): 
        return
    if os.path.getsize(path) / (1024*1024) > max_mb:
        Path(backup_dir).mkdir(parents=True, exist_ok=True) # <-- Crée ici
        ts = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = Path(path).name 
        new_path = Path(backup_dir) / filename.replace(".jsonl", f"-{ts}.jsonl")
        os.rename(path, new_path)ath)