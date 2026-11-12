import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import Welcome from './Welcome.vue'
import Login from './Login.vue'
import Account from './Account.vue'
import Logs from './Logs.vue'
import { auth, refresh } from './auth'
import './style.css'
const router = createRouter({ history: createWebHistory(), routes: [
  { path: '/', component: Welcome }, { path: '/connexion', component: Login },
  { path: '/espace', component: Account, meta: { private: true } },
  { path: '/journaux', component: Logs, meta: { private: true } },
] })
router.beforeEach(async to => {
  try {
    await refresh()
    if (to.meta.private && !auth.user) return { path: '/connexion', query: { retour: to.path } }
  } catch { if (to.meta.private) return { path: '/connexion', query: { retour: to.path } } }
})
createApp(App).use(router).mount('#app')
