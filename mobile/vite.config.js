import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
// Application mobile servie sous /mobile/ sur la même origine que l'API (session et jeton CSRF partagés).
// En local, le serveur Vite du site (port 5173) relaie /mobile vers ce serveur (port 5174).
export default defineConfig({
  base: '/mobile/',
  plugins: [vue()],
  // Scrutation des fichiers : sous Windows, Docker ne relaie pas les notifications de modification du dossier monté.
  server: { port: 5174, strictPort: true, hmr: { clientPort: 5173 }, watch: { usePolling: true, interval: 300 } },
})
