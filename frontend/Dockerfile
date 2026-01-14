FROM node:20-alpine

WORKDIR /app

# Kopírujeme pouze package.json (package-lock jsme smazali v kroku 0)
COPY package.json ./

# Čistá instalace závislostí
RUN npm install

# Kopírování zbytku zdrojového kódu
COPY . .

# Exponování portu
EXPOSE 5173

# SPUŠTĚNÍ: --host je nutný, aby Vite naslouchal mimo kontejner
CMD ["npm", "run", "dev", "--", "--host"]