# Application mobile (PWA) : compilation Vue, puis service des fichiers statiques par un Caddy interne (port 8080).
# Le Caddy public relaie /mobile/* vers ce conteneur : même origine que l'API, HTTPS assuré en façade.
FROM node:24-bookworm-slim@sha256:01d566e8d94d8c619591d78652babfccd3c6992a3dffa9ed5ab39e11cf6c70e9 AS build
WORKDIR /app
COPY mobile/package.json mobile/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY mobile/ ./
RUN npm run build

FROM caddy:2.10-alpine
COPY docker/mobile/Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist /srv/mobile
EXPOSE 8080
