FROM node:20-alpine

WORKDIR /app

# 1. dépendances
COPY package.json package-lock.json ./

# 2. code
COPY . .

EXPOSE 5173

CMD ["npm", "run", "dev","--","--host","0.0.0.0","--port","5173"]