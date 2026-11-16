<script setup>
// Liste des événements (équipe) avec recherche et filtre par statut ; l'administratrice peut en créer.
import { onMounted, reactive, ref } from 'vue'
import Icon from './Icon.vue'
import { useRoute } from 'vue-router'
import { auth, request } from './auth'

// Message transmis par l'adresse après la suppression d'un événement.
const notice = useRoute().query.message === 'supprime' ? 'Événement supprimé.' : ''
import { eventStatuses, localDate } from './teamFormat'

// Couleur de la pastille et du pavé selon le statut de l'événement.
const tones = { brouillon: 'grey', accepte: 'green', en_cours: 'blue', termine: 'yellow', annule: 'red' }

const events = ref([]),
  filter = reactive({ q: '', statut: '' }),
  loading = ref(true),
  error = ref('')

async function load() {
  loading.value = true
  error.value = ''
  try {
    events.value = (await request('/api/team/events?' + new URLSearchParams(filter))).evenements
  } catch {
    error.value = 'La liste des événements est indisponible pour le moment.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
<template>
  <main class="work-page">
    <p><RouterLink class="back-link" to="/espace">← Retour à mon espace</RouterLink></p>
    <div class="head">
      <span class="result-tile red" aria-hidden="true"><Icon name="calendar" /></span>
      <h1 class="list-title" style="--bar: #ffd836">Événements</h1>
      <RouterLink v-if="auth.user?.role === 'administrateur'" class="button-link" to="/equipe/evenements/nouveau"
        >Nouvel événement</RouterLink
      >
    </div>
    <p v-if="notice" class="success" role="status">{{ notice }}</p>
    <!-- Recherche et filtre par statut -->
    <form class="search-bar" role="search" @submit.prevent="load">
      <div class="search-field">
        <label for="events-q">Rechercher (nom, lieu, client)</label
        ><input id="events-q" v-model="filter.q" type="search" maxlength="100" /><Icon name="search" />
      </div>
      <div class="status-field">
        <label for="events-statut">Statut</label>
        <select id="events-statut" v-model="filter.statut" @change="load">
          <option value="">Tous</option>
          <option v-for="(label, key) in eventStatuses" :key="key" :value="key">{{ label }}</option>
        </select>
      </div>
      <button type="submit">Rechercher</button>
    </form>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-else role="status" class="count">{{ events.length }} événement{{ events.length > 1 ? 's' : '' }}.</p>
    <!-- Liste des événements : une ligne par événement -->
    <ul class="event-rows">
      <li v-for="e in events" :key="e.id">
        <span class="result-tile" :class="tones[e.statut]" aria-hidden="true"><Icon name="calendar" /></span>
        <h2>
          <RouterLink :to="'/equipe/evenements/' + e.id">{{ e.nom }}</RouterLink>
          <span class="sr-only"> — {{ eventStatuses[e.statut] || e.statut }}</span>
        </h2>
        <span class="cell"><Icon name="users" />{{ e.client }}</span>
        <span class="cell"><Icon name="calendar" />{{ localDate(e.debut) }}</span>
        <span class="cell"
          ><template v-if="e.lieu"><Icon name="pin" />{{ e.lieu }}</template></span
        >
        <span class="status-pill badge" :class="tones[e.statut]" aria-hidden="true">{{
          eventStatuses[e.statut] || e.statut
        }}</span>
      </li>
    </ul>
  </main>
</template>
<style scoped>
.head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 20px;
}
.head .list-title {
  flex: 1;
  margin: 8px 0;
}
.status-field {
  flex: 0 1 260px !important;
}
.count {
  font-weight: 700;
  font-size: 1.15rem;
}
.event-rows {
  margin: 0;
  padding: 0;
  list-style: none;
  background: #fbf8f1;
  border: 1px solid #d8cfbd;
  border-radius: 10px;
}
.event-rows li {
  display: grid;
  grid-template-columns: auto minmax(0, 1.6fr) minmax(0, 1.1fr) minmax(0, 1fr) minmax(0, 1.2fr) auto;
  gap: 8px 18px;
  align-items: center;
  margin: 0;
  padding: 10px 18px;
  border-top: 1px solid #e5dccb;
}
.event-rows li:first-child {
  border-top: 0;
}
.event-rows .result-tile {
  width: 38px;
  height: 38px;
  border-radius: 8px;
}
.event-rows .result-tile .icon {
  width: 22px;
  height: 22px;
}
h2 {
  margin: 0;
  font-size: 1.05rem;
  overflow-wrap: anywhere;
}
h2 a {
  color: #111;
  text-decoration: none;
}
h2 a:hover {
  text-decoration: underline;
}
.cell {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 0.95rem;
  overflow-wrap: anywhere;
}
.cell + .cell {
  padding-left: 14px;
  border-left: 1px solid #d8cfbd;
}
.cell .icon {
  width: 18px;
  height: 18px;
}
.button-link {
  display: inline-flex;
  align-items: center;
  min-height: 48px;
  padding: 12px 22px;
  border-radius: 6px;
  background: #c8221a;
  color: #fff;
  font-weight: 700;
  text-decoration: none;
}
.button-link:hover {
  background: #a51b14;
}
.success {
  color: #0b6b3a;
  font-weight: 600;
}
@media (max-width: 1000px) {
  .event-rows li {
    grid-template-columns: auto minmax(0, 1fr) auto;
  }
  .cell {
    grid-column: 2 / -1;
  }
  .cell + .cell {
    padding-left: 0;
    border-left: 0;
  }
  .status-pill {
    grid-row: 1;
    grid-column: 3;
  }
}
</style>
