<script setup>
import { onMounted, reactive, ref } from 'vue'
import { request } from './auth'
import { eventStatuses, localDate } from './teamFormat'
const events = ref([]), filter = reactive({ q: '', statut: '' }), loading = ref(true), error = ref('')
async function load() {
  loading.value = true; error.value = ''
  try { events.value = (await request('/api/team/events?' + new URLSearchParams(filter))).evenements }
  catch { error.value = 'La liste des événements est indisponible pour le moment.' } finally { loading.value = false }
}
onMounted(load)
</script>
<template>
  <main class="panel wide">
    <p><RouterLink to="/espace">← Retour à mon espace</RouterLink></p>
    <h1>Événements</h1>
    <form class="filters" role="search" @submit.prevent="load">
      <div><label for="events-q">Rechercher (nom, lieu, client)</label><input id="events-q" v-model="filter.q" type="search" maxlength="100"></div>
      <div><label for="events-statut">Statut</label>
        <select id="events-statut" v-model="filter.statut" @change="load"><option value="">Tous</option><option v-for="(label, key) in eventStatuses" :key="key" :value="key">{{ label }}</option></select></div>
      <button type="submit">Rechercher</button>
    </form>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-else role="status">{{ events.length }} événement{{ events.length > 1 ? 's' : '' }}.</p>
    <ul class="cards">
      <li v-for="e in events" :key="e.id">
        <h2><RouterLink :to="'/equipe/evenements/' + e.id">{{ e.nom }}</RouterLink> <span class="badge">{{ eventStatuses[e.statut] || e.statut }}</span></h2>
        <p class="meta">{{ e.client }} — {{ localDate(e.debut) }}<template v-if="e.lieu"> — {{ e.lieu }}</template></p>
      </li>
    </ul>
  </main>
</template>
<style scoped>
.filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0 16px; align-items: end }
.cards { list-style: none; padding: 0 }
.cards li { border: 1px solid #CBD5E1; border-radius: 8px; padding: 16px; margin: 12px 0 }
h2 { margin: 0 0 4px; font-size: 1.15rem; overflow-wrap: anywhere }
.badge { display: inline-block; margin-left: 4px; padding: 2px 8px; border-radius: 12px; background: #E6EEF3; font-size: .85rem; font-weight: 600 }
.meta { margin: 4px 0; color: #4A5B68; overflow-wrap: anywhere }
</style>
