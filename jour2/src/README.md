# Jour-02 | 27/06/2026

**Thème** : Fondations Python pour DevSecOps - Logging & OS  
**Statut** :  Terminé

---

## Objectif du Jour
Maîtriser `logging`, `os` et `pathlib` pour préparer l’automatisation de demain.

---

## J’ai Appris
1.  **`logging`** : Config d’un `FileHandler` pour écrire les logs dans `audit.log` au lieu du terminal. Format `%(asctime)s - %(levelname)s`.
2.  **`os`** : `os.makedirs()` avec `exist_ok=True`, `os.path.exists()`, `os.path.getsize()` pour gérer les fichiers.
3.  **`pathlib`** : Bases de `Path()` pour manipuler les chemins de façon plus propre que `os.path.join`.

**Contexte** : Connexion instable. Focus 100% modules natifs Python hors-ligne.

---

## J’ai Bloqué Sur
Rien de bloquant. Manque de connexion pour tester `subprocess` avec `nmap` en live.

---

##  J’ai Fait / Livrables
1.  Script test `test_logging.py` : Écriture de logs DEBUG/INFO/WARNING dans `audit.log`.
2.  Script test `test_os_pathlib.py` : Création auto du dossier `rapports/` et vérification de taille de fichier.

---

## English Log
**New words**: Logging, Handler, Directory, Size, Path  
**Sentence of the day**: I created the reports directory if it does not exist.

---

## Leçon Clé du Jour
La connexion peut tomber, la doc peut manquer. Mais `python -m pydoc os` marche toujours. Apprendre à apprendre hors-ligne.