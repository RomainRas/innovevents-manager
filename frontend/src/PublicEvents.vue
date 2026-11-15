<script setup>
// Page publique des événements, filtrable par période, type et thème.
// Les filtres sont gardés dans l'adresse : la page filtrée peut être partagée ou rechargée.
import { nextTick, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { request } from './auth'
import { period, statusLabels } from './publicFormat'

const route = useRoute(),
  router = useRouter()
const filters = reactive({ du: '', au: '', type: '', theme: '' })
const events = ref([]),
  types = ref([]),
  themes = ref([]),
  loading = ref(true),
  error = ref(''),
  errors = ref({})

/** Lit les filtres dans l'adresse puis charge les événements correspondants. */
async function load() {
  for (const key of Object.keys(filters)) filters[key] = typeof route.query[key] === 'string' ? route.query[key] : ''
  loading.value = true
  error.value = ''
  errors.value = {}
  try {
    const query = new URLSearchParams(Object.entries(filters).filter(([, v]) => v !== '')).toString()
    const data = await request('/api/public/events' + (query ? '?' + query : ''))
    events.value = data.events
    types.value = data.types
    themes.value = data.themes
  } catch (e) {
    events.value = []
    if (e.status === 422) {
      errors.value = e.fields
      error.value = 'Vérifiez les filtres signalés.'
    } else error.value = 'Les événements sont momentanément indisponibles. Réessayez plus tard.'
  } finally {
    loading.value = false
    await nextTick()
    document.getElementById('events-result')?.focus({ preventScroll: true })
  }
}

// Appliquer ou effacer les filtres revient à changer l'adresse ; le chargement suit (watch).
function apply() {
  router.replace({ query: Object.fromEntries(Object.entries(filters).filter(([, v]) => v !== '')) })
}

function reset() {
  router.replace({ query: {} })
}

// Recharge à chaque changement de l'adresse, et une première fois à l'ouverture.
watch(() => route.query, load, { immediate: true })
</script>
<template>
  <main class="panel wide">
    <h1>Nos événements</h1>
    <p>Une sélection d’événements organisés par Innov’Events, publiés avec l’accord de nos clients.</p>
    <!-- Filtres : période, type et thème -->
    <form class="filters" @submit.prevent="apply" aria-label="Filtrer les événements">
      <div>
        <label for="filter-du">Du</label
        ><input
          id="filter-du"
          v-model="filters.du"
          type="date"
          :aria-invalid="!!errors.du"
          :aria-describedby="errors.du ? 'filter-du-error' : undefined"
        />
        <p v-if="errors.du" id="filter-du-error" class="error">{{ errors.du }}</p>
      </div>
      <div>
        <label for="filter-au">Au</label
        ><input
          id="filter-au"
          v-model="filters.au"
          type="date"
          :aria-invalid="!!errors.au"
          :aria-describedby="errors.au ? 'filter-au-error' : undefined"
        />
        <p v-if="errors.au" id="filter-au-error" class="error">{{ errors.au }}</p>
      </div>
      <div>
        <label for="filter-type">Type d’événement</label>
        <select id="filter-type" v-model="filters.type">
          <option value="">Tous les types</option>
          <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
        </select>
      </div>
      <div>
        <label for="filter-theme">Thème</label>
        <select id="filter-theme" v-model="filters.theme">
          <option value="">Tous les thèmes</option>
          <option v-for="t in themes" :key="t" :value="t">{{ t }}</option>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit">Filtrer</button
        ><button type="button" class="secondary" @click="reset">Réinitialiser</button>
      </div>
    </form>
    <!-- Résultats, annoncés aux lecteurs d'écran à chaque changement -->
    <div id="events-result" tabindex="-1" aria-live="polite">
      <p v-if="loading">Chargement des événements…</p>
      <p v-else-if="error" class="error" role="alert">{{ error }}</p>
      <p v-else-if="!events.length">Aucun événement ne correspond à ces critères.</p>
      <template v-else>
        <p>{{ events.length }} événement{{ events.length > 1 ? 's' : '' }}</p>
        <ul class="event-cards">
          <li v-for="e in events" :key="e.id">
            <article>
              <img
                v-if="e.image"
                :src="`/api/public/event-image?id=${e.id}`"
                :alt="e.image_alternative || ''"
                width="1200"
                height="750"
                loading="lazy"
              />
              <div>
                <p class="tag">{{ statusLabels[e.statut] }}</p>
                <h2>
                  <RouterLink :to="`/evenements-publics/${e.id}`">{{ e.nom }}</RouterLink>
                </h2>
                <p>{{ period(e.debut, e.fin) }}</p>
                <p>{{ e.lieu }}</p>
                <p class="meta">
                  {{ e.type_evenement }}<template v-if="e.theme"> · Thème : {{ e.theme }}</template>
                </p>
              </div>
            </article>
          </li>
        </ul>
      </template>
    </div>
  </main>
</template>
