import { createApp } from 'vue'
import App from './App.vue'
import './style.css'
createApp(App).mount('#app')
// Service worker : installation de l'application et consultation hors ligne des dernières données.
if ('serviceWorker' in navigator) {
  addEventListener('load', () => { navigator.serviceWorker.register('/mobile/sw.js', { scope: '/mobile/' }).catch(() => { /* Application utilisable sans. */ }) })
}
