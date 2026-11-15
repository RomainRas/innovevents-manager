<script setup>
// Cadre de l'application mobile : session, choix de l'écran selon l'adresse, accès réservé à l'équipe.
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { state, session, logout, clearCachedData } from './api'
import LoginView from './LoginView.vue'
import EventsView from './EventsView.vue'
import EventView from './EventView.vue'
import ClientView from './ClientView.vue'

// Navigation par ancre (#/evenement/12) : fonctionne hors ligne sans configuration du serveur.
const hash = ref(location.hash)

addEventListener('hashchange', () => {
  hash.value = location.hash
})

// Écran à afficher : fiche événement, fiche contact (avec l'événement d'origine pour le retour) ou liste.
const route = computed(() => {
  let m = /^#\/evenement\/([1-9][0-9]{0,17})$/.exec(hash.value)
  if (m) return { name: 'event', id: m[1] }
  m = /^#\/client\/([1-9][0-9]{0,17})(?:\?evenement=([1-9][0-9]{0,17}))?$/.exec(hash.value)
  if (m) return { name: 'client', id: m[1], from: m[2] }
  return { name: 'events' }
})

const sessionError = ref('')

// Lecture de la session à l'ouverture ; hors ligne sans utilisateur mémorisé, l'écran de connexion s'affiche.
onMounted(async () => {
  try {
    await session()
  } catch {
    state.loaded = true
    sessionError.value = state.offline ? '' : 'Connexion au serveur impossible.'
  }
})

// Titre de page et focus sur le titre à chaque changement d'écran (lecteurs d'écran).
watch(route, async () => {
  await nextTick()
  document.querySelector('main h1')?.focus()
})

watch(
  () => state.user,
  u => {
    if (!u) clearCachedData()
  },
)

// Seuls les employés et l'administratrice utilisent l'application (accès mobile vérifié aussi par l'API).
const allowed = computed(() => state.user && ['employe', 'administrateur'].includes(state.user.role))

// Déconnexion : session fermée, données effacées du téléphone, retour à la liste.
async function signOut() {
  await logout()
  location.hash = ''
}
</script>
<template>
  <a class="skip" href="#contenu">Aller au contenu</a>
  <!-- Barre du haut (déconnexion) et bandeau hors ligne -->
  <header class="bar">
    <span class="brand">Innov’Events</span>
    <button v-if="state.user" type="button" class="link" @click="signOut">Se déconnecter</button>
  </header>
  <p v-if="state.offline" class="offline" role="status">
    Hors ligne : dernières informations enregistrées sur ce téléphone.
  </p>
  <!-- Écran courant : connexion, accès refusé, liste, fiche événement ou fiche contact -->
  <div id="contenu">
    <p v-if="!state.loaded" class="page" role="status">Chargement…</p>
    <template v-else-if="!state.user">
      <p v-if="sessionError" class="page error" role="alert">{{ sessionError }}</p>
      <LoginView />
    </template>
    <main v-else-if="!allowed" class="page">
      <h1 tabindex="-1">Accès réservé</h1>
      <p>
        L’application mobile est réservée à l’équipe Innov’Events. Votre espace client reste disponible sur le site.
      </p>
      <a class="button" href="/espace">Ouvrir le site</a>
    </main>
    <EventView v-else-if="route.name === 'event'" :id="route.id" :key="'e' + route.id" />
    <ClientView v-else-if="route.name === 'client'" :id="route.id" :from="route.from" :key="'c' + route.id" />
    <EventsView v-else />
  </div>
</template>
