import { createApp } from 'vue'
import { createRouter, createWebHistory } from 'vue-router'
import App from './App.vue'
import Welcome from './Welcome.vue'
import Login from './Login.vue'
import Account from './Account.vue'
import Logs from './Logs.vue'
import AccountForm from './AccountForm.vue'
import QuoteRequest from './QuoteRequest.vue'
import QuoteSettings from './QuoteSettings.vue'
import Prospects from './Prospects.vue'
import ProspectDetail from './ProspectDetail.vue'
import { auth, refresh } from './auth'
import './style.css'
const router = createRouter({ history: createWebHistory(), routes: [
  { path: '/', component: Welcome }, { path: '/connexion', component: Login },
  { path: '/demande-devis', component: QuoteRequest },
  { path: '/prospects', component: Prospects, meta: { private: true } },
  { path: '/prospects/:id', component: ProspectDetail, meta: { private: true } },
  { path: '/parametres-demandes', component: QuoteSettings, meta: { private: true } },
  { path: '/inscription', component: AccountForm, meta: { accountMode: 'register' } },
  { path: '/mot-de-passe-oublie', component: AccountForm, meta: { accountMode: 'forgot' } },
  { path: '/mot-de-passe', component: AccountForm, meta: { private: true, accountMode: 'password' } },
  { path: '/espace', component: Account, meta: { private: true } },
  { path: '/journaux', component: Logs, meta: { private: true } },
] })
router.beforeEach(async to => {
  try {
    await refresh()
    if (to.meta.private && !auth.user) return { path: '/connexion', query: { retour: to.path } }
    if (to.meta.private && auth.user?.password_change_required && to.path !== '/mot-de-passe') return '/mot-de-passe'
  } catch { if (to.meta.private) return { path: '/connexion', query: { retour: to.path } } }
})
createApp(App).use(router).mount('#app')
