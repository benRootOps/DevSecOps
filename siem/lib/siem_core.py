# siem_core.py
import argparse, yaml, logging
from pathlib import Path

LOG_LEVELS = {
    "DEBUG": logging.DEBUG,
    "INFO": logging.INFO,
    "WARNING": logging.WARNING,
    "ERROR": logging.ERROR,
    "CRITICAL": logging.CRITICAL
}

def load_config():
    """Charge config.yaml + args. Standard pour tout SIEM J9+"""
    parser = argparse.ArgumentParser(description="Mini-SIEM Semaine2")
    parser.add_argument("--config", required=True, help="Chemin vers config.yaml")
    args = parser.parse_args()

    with open(args.config, 'r') as f:
        config = yaml.safe_load(f)
    
    return config, args # on renvoie aussi args

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