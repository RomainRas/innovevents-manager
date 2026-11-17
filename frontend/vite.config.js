import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
export default defineConfig({
  plugins: [vue()],
  server: { port: 5173, strictPort: true, proxy: { '/api': 'http://api:8080', '/mobile': { target: 'http://mobile:5174', ws: true } } },
  // Tests unitaires du front (Vitest) : navigateur simulé par happy-dom, fichiers dans tests/.
  test: { environment: 'happy-dom', include: ['tests/**/*.test.js'] },
})
