🚀 Cloud-Issam

Application de gestion de notes universitaire conçue avec une architecture DevSecOps sécurisée, intégrant un SIEM temps réel capable de détecter, bannir et dé-bannir automatiquement les adresses IP malveillantes.

---

🔗 Repository

👉 https://github.com/benRootOps/DevSecOps

---

🧱 Stack Technique

Couche| Technologie
Frontend| React (Vite)
Backend| Django + Django REST Framework
Base de données| MariaDB
Reverse Proxy / WAF| OpenResty (Nginx + Lua)
Conteneurisation| Docker & Docker Compose
SIEM| Python (analyse logs + auto-ban/unban iptables)

---

🏗️ Architecture

Client
⬇
OpenResty (Reverse Proxy + WAF)
⬇
➡ Frontend React
➡ Backend Django API
⬇
MariaDB

🔍 SIEM (en parallèle)

- Analyse des logs OpenResty (attaques web)
- Analyse des logs SSH (bruteforce)
- Détection comportementale
- Bannissement automatique via "iptables"
- Dé-bannissement automatique après une durée définie

---

🔄 Flux de fonctionnement

1. Le client communique uniquement avec OpenResty
2. OpenResty sert le frontend et agit comme reverse proxy vers l’API
3. Le backend Django communique uniquement avec MariaDB
4. Le SIEM surveille les logs en temps réel
5. Les IP malveillantes sont bannies puis automatiquement réhabilitées après un délai configurable

---

⚙️ Installation & Lancement

Prérequis

- Docker
- Docker Compose

Installation

git clone https://github.com/benRootOps/DevSecOps.git
cd DevSecOps
docker-compose up --build -d

---

🌐 Accès

- Application : http://IP_SERVEUR:8080
- API Django : http://IP_SERVEUR:8080/api/

Logs SIEM

docker logs cloud-issam_siem_1 -f

---

🔐 Sécurité (SIEM)

Le module SIEM analyse :

📄 Logs Web (OpenResty)

- Détection de scans automatisés
- Détection de requêtes suspectes (SQL Injection, erreurs 404 massives)

🔐 Logs SSH

- Détection de tentatives de bruteforce

---

🚫 Politique de bannissement

- 🔴 Après 10 comportements suspects → IP bannie
- ⏳ Bannissement temporaire (durée configurable)
- 🟢 Unban automatique après expiration
- 🔁 Système adaptable (blacklist dynamique)

---

🧪 Attaques testées

- ✅ Bruteforce SSH
- ✅ Scan d’API (multiples 404)
- ✅ Requêtes suspectes
- 🔄 SQL Injection (en amélioration)

---

📁 Structure du projet

DevSecOps/
└── cloud-issam/
    ├── frontend/       # Application React
    ├── backend/        # API Django
    ├── database/       # Données MariaDB
    ├── openresty/      # Configuration reverse proxy
    ├── siem/           # Moteur SIEM (ban/unban)
    ├── logs/           # Logs partagés
    └── docker-compose.yml

---

🧠 Points forts techniques

- Architecture microservices conteneurisée
- Reverse proxy sécurisé avec OpenResty
- SIEM custom avec détection temps réel
- Auto-ban + auto-unban dynamique
- Isolation stricte des services
- Surveillance des logs système et applicatifs

---

🚧 Améliorations futures

- Détection avancée SQLi / XSS
- Dashboard SIEM (visualisation des attaques)
- Intégration CI/CD avec scans sécurité
- Alerting (email / webhook / Slack)
- Rate limiting via OpenResty

---

👨‍💻 Auteur

MBA Joseph Benjamin
DevSecOps Enthusiast | Cybersécurité

GitHub : https://github.com/benRootOps

---

📄 Licence

MIT