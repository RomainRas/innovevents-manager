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
import ClientConversion from './ClientConversion.vue'
import ClientDetail from './ClientDetail.vue'
import EventDraft from './EventDraft.vue'
import QuoteDraft from './QuoteDraft.vue'
import QuotesOverview from './QuotesOverview.vue'
import PublicEvents from './PublicEvents.vue'
import PublicEvent from './PublicEvent.vue'
import Reviews from './Reviews.vue'
import Contact from './Contact.vue'
import Legal from './Legal.vue'
import { auth, refresh } from './auth'
import './style.css'
const router = createRouter({ history: createWebHistory(), routes: [
  { path: '/', component: Welcome }, { path: '/connexion', component: Login },
  { path: '/demande-devis', component: QuoteRequest, meta: { title: 'Demande de devis' } },
  { path: '/evenements-publics', component: PublicEvents, meta: { title: 'Nos événements' } },
  { path: '/evenements-publics/:id', component: PublicEvent, meta: { title: 'Événement' } },
  { path: '/avis', component: Reviews, meta: { title: 'Avis clients' } },
  { path: '/contact', component: Contact, meta: { title: 'Contact' } },
  { path: '/mentions-legales', component: Legal, meta: { legal: 'mentions', title: 'Mentions légales' } },
  { path: '/cgu', component: Legal, meta: { legal: 'cgu', title: 'Conditions générales d’utilisation' } },
  { path: '/cgv', component: Legal, meta: { legal: 'cgv', title: 'Conditions générales de vente' } },
  { path: '/confidentialite', component: Legal, meta: { legal: 'confidentialite', title: 'Politique de confidentialité' } },
  { path: '/prospects', component: Prospects, meta: { private: true } },
  { path: '/prospects/:id', component: ProspectDetail, meta: { private: true } },
  { path: '/prospects/:id/convertir', component: ClientConversion, meta: { private: true } },
  { path: '/clients/:id', component: ClientDetail, meta: { private: true } },
  { path: '/prospects/:id/evenement', component: EventDraft, meta: { private: true, eventMode: 'create' } },
  { path: '/evenements/:id/devis', component: QuoteDraft, meta: { private: true } },
  { path: '/devis', component: QuotesOverview, meta: { private: true } },
  { path: '/evenements/:id', component: EventDraft, meta: { private: true, eventMode: 'edit' } },
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
// Titre de page explicite pour chaque écran (accessibilité : RGAA critère 8.6).
router.afterEach(to => { document.title = (to.meta.title ? to.meta.title + ' — ' : '') + 'Innov’Events' })
createApp(App).use(router).mount('#app')
