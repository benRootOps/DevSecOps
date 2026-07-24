# siem_core.py
import argparse, yaml, logging
from pathlib import Path
import os

LOG_LEVELS = {
    "DEBUG": logging.DEBUG,
    "INFO": logging.INFO,
    "WARNING": logging.WARNING,
    "ERROR": logging.ERROR,
    "CRITICAL": logging.CRITICAL
}

def load_config(config_file=None): 
    """Charge config.yaml depuis fichier ou variable d'env. Standard pour tout SIEM J9+"""
    parser = argparse.ArgumentParser(description="SIEM CLOUD ISSAM")
    parser.add_argument("--config", help="Chemin vers config.yaml")
    args, unknown = parser.parse_known_args() # <- parse_known_args pour pas crash si y'a d'autres args
    
    # Priorité 1: --config en CLI
    # Priorité 2: variable d'env CONFIG_FILE
    # Priorité 3: valeur par défaut ABSOLUE
    final_config_file = args.config or config_file or os.getenv('CONFIG_FILE') or '/app/etc/config.yaml'
    
    print(f"[SIEM] Chargement config: {final_config_file}") # <- debug pour voir quel fichier il prend
    
    with open(final_config_file, 'r') as f:
        config = yaml.safe_load(f)
    return config, args


def setup_logging(config):
    """Setup logging avec niveau depuis config.yaml"""
    level_str = config.get("log_level", "INFO").upper() # valeur par défaut INFO
    level = LOG_LEVELS.get(level_str, logging.INFO) # fallback si mauvaise valeur

    logging.basicConfig(
        level=level, 
        format="%(asctime)s - %(name)s - %(levelname)s - %(message)s", # j'ai ajouté name
        datefmt='%Y-%m-%d %H:%M:%S',
        force=True # force la re-conf si appelé 2 fois
    )
    logger = logging.getLogger("SIEM") # nom propre au lieu de __name__
    logger.info(f"Niveau de log défini sur: {level_str}")
    return logger