# Jour-01 | 26/06/2026

**Thème** : Introduction au DevSecOps & Setup Environnement Kali Linux  
**Statut** : ✅ Terminé

---

## 🎯 Objectif du Jour
Comprendre le rôle d’un DevSecOps et mettre en place l’environnement de travail avec les bases Linux + Python.

---

## ✅ J’ai Appris

1.  **C’est quoi un DevSecOps** : 
    Un ingénieur hybride. Son rôle est d’intégrer la sécurité `Security` dès la phase de développement `Dev` et d’opération `Ops`. Il doit maîtriser : Réseau, Sécurité, et Développement d’applications.

2.  **Bases Linux** : 
    Navigation dans le terminal, commandes de base indispensables pour un Junior DevSecOps.

3.  **Commande `ss`** : 
    Utilisée pour lister les sockets ouverts. Elle permet de voir les ports en écoute, l’adresse IP source `Local Address` et l’adresse IP destination `Peer Address`.

4.  **Python pour l’Automatisation** : 
    Modules `subprocess` pour exécuter des commandes système, et `argparse` avec `subparsers` pour créer des CLI avec plusieurs sous-commandes.

---

## ⚠️ J’ai Bloqué Sur

1.  **`iptables`** : Difficulté sur la compréhension des chaînes `INPUT`, `OUTPUT`, `FORWARD`. 
    **Cause** : Barrière de langue sur la documentation en anglais.
    **Action** : À revoir Jour-03 avec des schémas + `man iptables`.

---

## 💻 J’ai Fait / Livrables

1.  **Setup Environnement** : Installation et config de Python + outils de base sur Kali Linux.
2.  **Projets Pratiques Python** dans `/src` :
    -   `ping.py` : Script de ping automatisé avec `subprocess`.
    -   `scanNmap.py` : Script de scan réseau basique avec `subprocess`.
    -   `scanner_subparser.py` : Outil CLI qui combine les 2 scripts via `argparse` et `subparsers`.

**Code Source** : Voir le dossier [`./src`](./src)

---

## 📚 Leçon Clé du Jour
La théorie sans pratique ne sert à rien. Construire 3 mini-scripts m’a forcé à comprendre `subprocess` et `argparse` 10x plus vite que la doc seule.

---

## ⏭️ Prochaine Étape | Jour-02
Approfondir `iptables` et les règles de pare-feu de base.
