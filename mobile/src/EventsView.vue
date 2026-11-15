<script setup>
// Tableau de bord mobile : événements à venir ou en cours, du plus proche au plus lointain.
import { onMounted, ref } from 'vue'
import { api, day, state, statuses, when } from './api'

const events = ref([]),
  loading = ref(true),
  error = ref('')

/** Charge la liste ; une session expirée (401) ramène à l'écran de connexion. */
async function load() {
  loading.value = true
  error.value = ''
  try {
    events.value = (await api('/api/mobile/events')).evenements
  } catch (e) {
    if (e.status === 401) {
      state.user = null
      return
    }
    error.value =
      e.status === 403
        ? 'L’accès mobile n’est pas activé pour votre compte. Demandez-le à l’administratrice.'
        : e.status === 503 || e.status === 0
          ? 'Pas de connexion et aucune donnée enregistrée sur ce téléphone.'
          : 'Les événements sont indisponibles pour le moment.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
<template>
  <main class="page">
    <h1 tabindex="-1">Événements à venir</h1>
    <p class="hello">Bonjour {{ state.user.prenom }}</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" role="status">Chargement…</p>
    <template v-else-if="!error">
      <p role="status" class="count">
        {{
          events.length
            ? `${events.length} événement${events.length > 1 ? 's' : ''} à venir`
            : 'Aucun événement à venir.'
        }}
      </p>
      <!-- Événements à venir ou en cours, avec pastille de date -->
      <ul class="events">
        <li v-for="e in events" :key="e.id">
          <a :href="'#/evenement/' + e.id" class="event-card">
            <span class="date" aria-hidden="true"
              ><b>{{ day(e.debut).d }}</b
              >{{ day(e.debut).m }}</span
            >
            <span class="body">
              <span class="name">{{ e.nom }}</span>
              <span class="meta">{{ e.client }}</span>
              <span class="meta">{{ when(e.debut, e.fin) }}</span>
              <span v-if="e.lieu" class="meta">{{ e.lieu }}</span>
              <span class="badge" :class="e.statut">{{ statuses[e.statut] || e.statut }}</span>
            </span>
          </a>
        </li>
      </ul>
      <button type="button" class="secondary wide" :disabled="loading" @click="load">Actualiser</button>
    </template>
  </main>
</template>
