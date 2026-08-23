<div align="center">

# ☁️ Cloud-ISSAM

**Plateforme cloud sécurisée de gestion de notes académiques**
Architecture microservices containerisée, orchestrée par Kubernetes (k3s), avec détection d'intrusion intégrée.
</div>

---

## 📖 À propos

**Cloud-ISSAM** est une plateforme de gestion de notes académiques (niveaux, filières, spécialités, matières, enseignants, étudiants) construite comme un vrai système en conditions de production plutôt que comme un exercice académique classique.

Le projet ne se limite pas à une application web : il inclut sa propre **couche de sécurité et de détection d'intrusion** (SIEM custom), un **reverse proxy** en frontal, et un **déploiement entièrement containerisé** orchestré par Kubernetes (k3s) — avec un pipeline CI/CD automatisé.

L'objectif : appliquer une approche **DevSecOps** de bout en bout, de l'écriture du code jusqu'au monitoring de sécurité en production.

---

## 🏗️ Architecture

<div align="center">
<img src="docs/architecture.png" alt="Diagramme d'architecture Cloud-ISSAM" width="850">
</div>

### Flux principal

1. Le client accède à l'application via **HTTPS** sur le port `30080` (OpenResty)
2. **OpenResty** route les requêtes vers le frontend, termine le TLS, journalise chaque accès
3. Le **frontend React** consomme l'API exposée par OpenResty
4. OpenResty route les requêtes API vers le **backend Django REST**
5. Le backend interagit avec **MySQL**, externe au cluster (conteneur Docker sur l'hôte)
6. Les **agents SIEM** (déployés en `DaemonSet`, une instance par nœud) lisent en continu les logs OpenResty (accès/erreurs) et les logs SSH (`/var/log/auth.log`), détectent les comportements suspects et déclenchent un **ban automatique via iptables**, avec **unban** après un délai configuré

### Composants orchestrés dans le cluster k3s

| Composant | Rôle |
|---|---|
| **Frontend** (React / Vite) | Interface utilisateur, servie en Pod(s) derrière un Service `ClusterIP` (`:5173`) |
| **Backend** (Django REST Framework) | API REST, Pod(s) derrière un Service `ClusterIP` (`:8000`) |
| **CoreDNS** | Résolution DNS interne au cluster |
| **Agents SIEM** (DaemonSet) | Collecte des logs et surveillance sur chaque nœud |
| **Local Path Provisioner** | Stockage local persistant |
| **Metrics Server** | Métriques du cluster |
| **OpenResty** — serveur web et reverse proxy, en frontal du cluster



### Composants externes (non orchestrés)
-**MySQL** — base de données, conteneur Docker sur l'hôte

---

## 🔐 Sécurité — SIEM en action

Le SIEM custom (module `siem/`) fonctionne en deux agents distincts :

- **SIEM Web** — lit les logs OpenResty, détecte les activités malveillantes (scraping agressif, endpoints suspects, dépassement de seuil de requêtes), bannit automatiquement l'IP concernée
- **SIEM SSH** — lit `/var/log/auth.log`, détecte les tentatives de force brute sur les connexions SSH

**Fonctionnement du blocage automatique :**
- Chaque règle définit un **seuil** et une **fenêtre de temps** (ex. 10 requêtes en 60 secondes)
- Au-delà du seuil → **ban automatique** de l'IP via `iptables`
- Après un délai configuré → **unban automatique**
- Liste blanche (`WHITELIST`) pour les IP de confiance (loopback, réseau interne du cluster)
- Tableau de bord web pour visualiser les IP bannies et l'activité en temps réel

> Exemple réel de log SIEM : une IP dépassant `rule_6` (10 requêtes / 60s) déclenche `[BLOCAGE]` automatique après reconstitution de l'historique récent des accès.

---

## 📂 Structure du dépôt

```
cloud-issam/
├── .github/
│   └── workflows/
│       └── cicd.yml        # Pipeline CI/CD (build, tests, déploiement)
├── frontend/                # Application React (Vite) — interface enseignant / admin
├── backend/                  # API Django REST Framework
├── openresty/                # Configuration du reverse proxy (nginx/Lua)
├── kubernetes/                # Manifests k3s (Deployments, Services, DaemonSet SIEM, Ingress...)
├── siem/                      # Agents de détection d'intrusion (SIEM Web + SIEM SSH)
├── logs/                      # Logs générés en environnement Docker Compose (local/dev)
└── docker-compose.yaml       # Orchestration locale pour développement
```

---

## ⚙️ CI/CD

Le pipeline défini dans [`.github/workflows/cicd.yml`](.github/workflows/cicd.yml) automatise :

- Le **build** des images Docker (frontend, backend, openresty, siem)
- L'exécution des **tests** et **vérifications** avant intégration
- Le **déploiement** des manifests vers le cluster k3s

> 📝 Cette section décrit l'objectif du pipeline — n'hésite pas à l'ajuster avec le détail exact des étapes (linting, scan de vulnérabilités, tests unitaires...) telles qu'elles sont définies dans ton fichier `cicd.yml`.

---

## 🚀 Démarrage

### Option 1 — Développement local (Docker Compose)

```bash
git clone https://github.com/benRootOps/cloud-issam.git
cd cloud-issam
docker-compose up -d
```

Les logs de chaque service sont disponibles dans le dossier `logs/`.

Accès par défaut :
- Frontend : `http://localhost:<port_frontend>`
- API Backend : `http://localhost:<port_backend>`

### Option 2 — Déploiement Kubernetes (k3s)

```bash
# Installer k3s (si nécessaire)
curl -sfL https://get.k3s.io | sh -

# Appliquer les manifests
kubectl apply -f kubernetes/
```

Vérifier le déploiement :

```bash
kubectl get pods -A
kubectl get svc -A
```

```ACTION A VERIFIER

Si le backend ne communique pas encore votre bd œuvrer le 
fichier settings.py et assurer vous de mettre l'adresse ip de votre serveur
dans la section HOST: 'votre Ip serveur'. La co figuration openresty
doit également contenir les adresses IP du clusterIp frontend et backend
faite **kubectl get svc** et récupérer les différente IP concerner et remplacer les.
L'application est accessible via OpenResty sur le port `30080`.
````


---

## 🖥️ Aperçu

| Connexion | Dashboard admin |
|---|---|
| ![login](docs/screenshots/login.png) | ![dashboard](docs/screenshots/dashboard.png) |

| Gestion des enseignants | Saisie des notes |
|---|---|
| ![enseignants](docs/screenshots/enseignants.png) | ![notes](docs/screenshots/notes.png) |

*(images à placer dans `docs/screenshots/` dans le dépôt)*

---

## 🧰 Stack technique

**Frontend** — React, Vite
**Backend** — Python, Django REST Framework
**Base de données** — MySQL
**Infrastructure** — Docker, Docker Compose, Kubernetes (k3s)
**Reverse proxy** — OpenResty
**Sécurité** — SIEM custom (Python), iptables, détection d'intrusion
**CI/CD** — GitHub Actions

---

## 🗺️ Pistes d'amélioration

- [ ] Kubernetes — réseau avancé (NetworkPolicies, service mesh)
- [ ] Pipeline CI/CD — étapes de scan de sécurité automatisées
- [ ] Cloud security — durcissement supplémentaire (least-privilege, secrets management)

---

## 👤 Auteur

**MBA Joseph Benjamin** — DevSecOps Junior · Cloud & Security Engineer
📍 Yaoundé, Cameroun
📧 josephbenjaminmba@gmail.com
🔗 [LinkedIn](https://www.linkedin.com/in/joseph-benjamin-m-2a1982367) · [GitHub](https://github.com/benRootOps)
d'intrusion
**CI/CD** — GitHub Actions

---

## 🗺️ Pistes d'amélioration

- [ ] Kubernetes — réseau avancé (NetworkPolicies, service mesh)
- [ ] Pipeline CI/CD — étapes de scan de sécurité automatisées
- [ ] Cloud security — durcissement supplémentaire (least-privilege, secrets management)

---

## 👤 Auteur

**MBA Joseph Benjamin** — DevSecOps Junior · Cloud & Security Engineer
📍 Yaoundé, Cameroun
📧 josephbenjaminmba@gmail.com
🔗 [LinkedIn](https://www.linkedin.com/in/joseph-benjamin-m-2a1982367) · [GitHub](https://github.com/benRootOps)
