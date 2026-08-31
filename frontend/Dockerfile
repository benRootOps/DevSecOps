FROM node:20-alpine

WORKDIR /app

# Copie uniquement les manifests d'abord (meilleur cache Docker)
COPY package.json package-lock.json ./

# Install DANS le conteneur — résout les bons binaires natifs pour musl/Alpine
RUN npm install

# Copie le reste du code après
COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0", "--port", "5173"]