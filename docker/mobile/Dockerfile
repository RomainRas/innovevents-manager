# Application mobile (PWA) : compilation Vue, puis service des fichiers statiques par un Caddy interne (port 8080).
# Le Caddy public relaie /mobile/* vers ce conteneur : même origine que l'API, HTTPS assuré en façade.
FROM node:26-bookworm-slim@sha256:5df79989ed685e09f0a98019db4cebfb22fb9df1a53c0161664a011f7ff6b0f1 AS build
WORKDIR /app
COPY mobile/package.json mobile/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY mobile/ ./
RUN npm run build

FROM caddy:2.10-alpine
COPY docker/mobile/Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist /srv/mobile
EXPOSE 8080
