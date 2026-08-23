# Cloud-ISSAM — Journal de débogage k3s

Récapitulatif des bugs rencontrés lors de la mise en place du cluster k3s (Août 2026), leur diagnostic et leur résolution. Ce document sert de référence pour éviter de retomber dans les mêmes pièges.

---

## Contexte du cluster

- VM Ubuntu Server (`devsecops`) sous VirtualBox, IP interne `10.0.2.15`
- k3s single-node, CNI flannel, CoreDNS `1.14.4`
- Images gérées manuellement : `docker build` → `docker save` → `k3s ctr images import` (pas de registry)
- Composants : `frontend` (React/Vite), `backend` (Django), `openresty` (reverse proxy), `siem-web`/`siem-ssh` (DaemonSet), MariaDB externe au cluster (conteneur Docker sur la VM)

---

## Bug 1 — 502 Bad Gateway : blocage réseau cluster-wide (kube-router)

**Symptôme**
Aucune connectivité pod-à-pod, même par IP directe (`/dev/tcp` timeout systématique). `kubectl run` pour des pods de test (busybox) restait bloqué en `Pending`/timeout.

**Diagnostic**
```bash
sudo iptables -L FORWARD -v -n
# policy DROP, chaîne KUBE-ROUTER-FORWARD présente
sudo iptables -L KUBE-POD-FW-<hash> -v -n
# la chaîne se terminait par un REJECT faute de mark 0x10000/0x10000
```
Le network policy controller de **kube-router** (activé par défaut avec k3s) appliquait un comportement **default-deny** sur tout le trafic FORWARD, alors qu'aucune `NetworkPolicy` n'était définie dans le cluster (`kubectl get networkpolicy -A` → vide). Comportement anormal : "0 policy" devrait vouloir dire "tout autorisé".

**Résolution**
```bash
sudo mkdir -p /etc/rancher/k3s
sudo tee /etc/rancher/k3s/config.yaml > /dev/null << 'EOF'
disable-network-policy: true
EOF
sudo systemctl restart k3s
```
Puis purge manuelle des anciennes chaînes iptables résiduelles (le controller désactivé ne supprime pas automatiquement ce qu'il avait déjà créé) :
```bash
sudo iptables -D FORWARD -j KUBE-ROUTER-FORWARD
sudo iptables -F KUBE-ROUTER-FORWARD
sudo iptables -X KUBE-ROUTER-FORWARD
# + purge des sous-chaînes KUBE-POD-FW-* et KUBE-NWPLCY-*
```

**Piège rencontré en cours de route** : `systemctl edit k3s` (override systemd) a été essayé en premier mais le fichier `ExecStart=` a été mal sauvegardé plusieurs fois (fichier vide, ligne manquante). La méthode `/etc/rancher/k3s/config.yaml` est plus fiable et recommandée officiellement par k3s.

---

## Bug 2 — Frontend crash : module Rollup natif manquant

**Symptôme**
```
Error: Cannot find module @rollup/rollup-linux-x64-musl
```

**Diagnostic**
Bug connu npm avec les `optionalDependencies` : les binaires natifs par plateforme (musl vs glibc) ne s'installent pas correctement si `node_modules` a été généré sur un environnement différent puis copié dans l'image.

**Résolution**
S'assurer que `npm install`/`npm ci` s'exécute **dans** le contexte de build Docker (jamais copier un `node_modules` préexistant depuis l'host) :
```dockerfile
FROM node:20-alpine
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm install
COPY . .
CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]
```
`.dockerignore` doit exclure `node_modules`. Rebuild avec `--no-cache` pour forcer une réinstallation propre.

---

## Bug 3 — Frontend 403 : Vite bloque le header Host

**Symptôme**
```
Blocked request. This host ("frontend-service") is not allowed.
```

**Diagnostic**
Vite 5+ bloque par défaut les requêtes dont le header `Host` n'est pas explicitement autorisé (protection anti DNS-rebinding).

**Résolution**
Dans `vite.config.js` :
```js
server: {
  host: "0.0.0.0",
  port: 5173,
  allowedHosts: true, // ou liste explicite en prod
}
```
⚠️ Piège annexe : `plugins: [react(), tailwindcss()]` avait été oublié dans `defineConfig({...})` — sans le plugin React actif, Vite ne transforme pas le JSX correctement → `Uncaught ReferenceError: React is not defined` dans le navigateur.

---

## Bug 4 — 502 persistant : mauvais nom d'image dans le Deployment

**Symptôme**
Malgré plusieurs rebuilds de l'image OpenResty, le contenu de `nginx.conf` dans le pod ne changeait jamais.

**Diagnostic**
```bash
kubectl get deployment openresty -o yaml | grep image
# "image":"cloud-issam-openresty:latest"
```
L'image buildée localement s'appelait `openresty-cloud-issam:latest` (ordre inversé) — le deployment pointait vers un nom totalement différent, donc chaque rebuild n'avait aucun effet sur le pod réellement actif.

**Résolution**
```bash
kubectl set image deployment/openresty openresty=openresty-cloud-issam:latest
```
**Leçon** : toujours vérifier `kubectl get deployment <name> -o yaml | grep image:` avant de suspecter un problème de cache Docker/containerd.

---

## Bug 5 — 502 intermittent : resolver DNS OpenResty incompatible avec CoreDNS

**Symptôme**
```
frontend-service could not be resolved (2: Server failure)
unexpected DNS response for frontend-service
```
alors que la résolution DNS fonctionnait parfaitement pour tout autre client du cluster (testé et confirmé avec un pod busybox).

**Diagnostic**
Le module `resolver` natif d'OpenResty/nginx a un comportement de parsing incompatible avec certaines réponses de CoreDNS `1.14.4` (probablement lié à EDNS/IPv6). `resolver ... ipv6=off` a réduit le bruit mais n'a pas complètement réglé le problème.

**Résolution (pragmatique, single-node dev)**
Abandon du resolver dynamique, remplacement par les **ClusterIP statiques** dans `proxy_pass` :
```nginx
location / {
    proxy_pass http://10.43.113.233:5173;  # ClusterIP fixe de frontend-service
    ...
}
location /api/ {
    proxy_pass http://10.43.25.130:8000;   # ClusterIP fixe de backend-service
    ...
}
```
⚠️ **Limite connue** : ces IPs doivent être mises à jour manuellement si les services sont un jour supprimés/recréés (elles restent stables tant que le service existe).

---

## Bug 6 — Logs OpenResty invisibles via `kubectl logs`

**Symptôme**
`kubectl logs <pod-openresty>` retournait toujours vide, même en présence d'erreurs actives.

**Diagnostic**
```bash
cat nginx.conf | grep log
# error_log /var/log/nginx/error.log notice;
# access_log /var/log/nginx/access.log json;
```
Les logs sont écrits dans des **fichiers**, pas vers stdout/stderr — `kubectl logs` ne lit que la sortie du process PID 1.

**Résolution**
```bash
kubectl exec -it <pod> -- tail -f /var/log/nginx/access.log
kubectl exec -it <pod> -- tail -f /var/log/nginx/error.log
```

---

## Bug 7 — Backend 502 : connexion MySQL à un host Docker inexistant dans k3s

**Symptôme**
```
django.db.utils.OperationalError: (2003, "Can't connect to MySQL server on 'mariadb_central' ([Errno -2] Name or service not known)")
```

**Diagnostic**
`settings.py` contenait `'HOST': 'mariadb_central'` — un nom de conteneur Docker valide dans l'ancien environnement `docker-compose` (réseau Docker partagé), mais **inexistant** dans le cluster k3s (aucun Service Kubernetes ne porte ce nom, CoreDNS ne peut pas le résoudre).

**Contexte réel** : MariaDB tourne comme conteneur Docker classique **externe au cluster**, directement sur la VM hôte, port `3306` exposé sur `0.0.0.0`.

**Résolution**
```python
DATABASES = {
    'default': {
        'ENGINE': 'django.db.backends.mysql',
        'HOST': '10.0.2.15',  # IP de la VM hôte, pas le nom du conteneur
        'PORT': '3306',
        ...
    }
}
```

---

## Bug 8 — Erreur de contexte de build Docker

**Symptôme**
```
ERROR: failed to compute cache key: "/backend": not found
```

**Diagnostic**
Le Dockerfile du backend attend d'être buildé depuis la **racine du projet** (il référence `deps/` et `backend/requirements.txt` comme sous-dossiers), mais la commande avait été lancée avec `./backend` comme contexte.

**Résolution**
```bash
cd ~/DevSecOps/cloud-issam
docker build --no-cache -t cloud-issam-backend:latest -f backend/Dockerfile .
```
Contexte = racine (`.`), Dockerfile précisé explicitement avec `-f`.

---

## Procédure standard de rebuild/redeploy (leçon transverse)

Pour éviter de reproduire le Bug 4 (image jamais mise à jour), toujours suivre ce cycle complet :

```bash
# 1. Build sans cache
docker build --no-cache -t <image>:latest -f <path>/Dockerfile <context>

# 2. Vérifier le contenu AVANT d'importer
docker run --rm <image>:latest cat <fichier-modifié>

# 3. Sauvegarder
docker save <image>:latest -o <image>.tar

# 4. Purger l'ancien digest dans containerd
sudo k3s ctr images rm docker.io/library/<image>:latest

# 5. Importer le nouveau tar
sudo k3s ctr images import <image>.tar

# 6. Confirmer que le digest a changé
sudo k3s ctr images ls | grep <image>

# 7. Forcer un nouveau pod (pas juste rollout restart)
kubectl delete pod -l app=<app>

# 8. Vérifier le contenu dans le pod final
kubectl exec -it <nouveau-pod> -- cat <fichier-modifié>
```

---

## Outils de diagnostic utiles retenus

| Besoin | Commande |
|---|---|
| Tester TCP entre pods sans curl/wget | `bash -c 'cat < /dev/tcp/<ip>/<port>'` (nécessite bash dans l'image) |
| Tester une vraie requête HTTP sans curl | `exec 3<>/dev/tcp/<ip>/<port> && echo -e "GET / HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n" >&3 && cat <&3` |
| Capturer le trafic réseau en direct | `sudo tcpdump -i any host <ip> and port <port> -n` |
| Voir où un paquet est droppé dans iptables | `sudo iptables -Z FORWARD` puis test, puis `iptables -L FORWARD -v -n` |
| Image busybox locale disponible sur k3s | `sudo k3s ctr images ls \| grep busybox` (souvent `rancher/mirrored-library-busybox`, pas `busybox:latest`) |
