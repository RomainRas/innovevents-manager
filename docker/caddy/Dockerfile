# Compilation de l'application Vue, puis service par Caddy (HTTPS automatique).
FROM node:24-bookworm-slim@sha256:01d566e8d94d8c619591d78652babfccd3c6992a3dffa9ed5ab39e11cf6c70e9 AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

FROM caddy:2.10-alpine
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist /srv
