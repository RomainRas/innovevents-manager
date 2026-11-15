# Compilation de l'application Vue, puis service par Caddy (HTTPS automatique).
FROM node:26-bookworm-slim@sha256:5df79989ed685e09f0a98019db4cebfb22fb9df1a53c0161664a011f7ff6b0f1 AS build
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npm run build

FROM caddy:2.10-alpine
COPY docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY --from=build /app/dist /srv
