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

/** Période d'une carte : date et horaires sur deux lignes quand l'événement tient sur une journée. */
function cardPeriod(e) {
  const text = period(e.debut, e.fin),
    cut = text.indexOf(', de ')
  return cut > 0 ? [text.slice(0, cut), text.slice(cut + 2)] : [text, '']
}

// Couleur du bandeau de statut posé sur la photo de chaque carte.
const statusClass = { en_cours: 'yellow', accepte: 'blue', termine: 'red', annule: 'grey' }

// Recharge à chaque changement de l'adresse, et une première fois à l'ouverture.
watch(() => route.query, load, { immediate: true })
</script>
<template>
  <main class="poster-page events-page">
    <!-- En-tête : titre surligné, présentation et photo d'ambiance en noir et blanc -->
    <div class="events-head">
      <h1 class="poster-title yellow">Nos événements</h1>
      <p class="intro">Une sélection d’événements organisés par Innov’Events, publiés avec l’accord de nos clients.</p>
      <div class="head-photo" aria-hidden="true">
        <span class="shape red torn-a"></span>
        <span class="shape blue torn-b"></span>
        <img src="/images/accueil-soiree-danse.webp" alt="" width="1600" height="900" />
      </div>
    </div>
    <!-- Filtres : période, type et thème -->
    <form class="filters filter-bar" @submit.prevent="apply" aria-label="Filtrer les événements">
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
        <p class="count">{{ events.length }} événement{{ events.length > 1 ? 's' : '' }}</p>
        <ul class="event-cards">
          <li v-for="e in events" :key="e.id">
            <article>
              <div class="photo">
                <img
                  v-if="e.image"
                  :src="`/api/public/event-image?id=${e.id}`"
                  :alt="e.image_alternative || ''"
                  width="1200"
                  height="750"
                  loading="lazy"
                />
                <p class="status" :class="statusClass[e.statut]">{{ statusLabels[e.statut] }}</p>
              </div>
              <div class="body">
                <h2>
                  <RouterLink :to="`/evenements-publics/${e.id}`">{{ e.nom }}</RouterLink>
                </h2>
                <!-- Informations pratiques, chacune précédée d'un pictogramme décoratif -->
                <ul class="facts">
                  <li>
                    <svg aria-hidden="true" viewBox="0 0 24 24">
                      <rect x="3" y="5" width="18" height="16" rx="2" />
                      <path d="M3 10h18M8 3v4M16 3v4" /></svg
                    >{{ cardPeriod(e)[0] }}
                  </li>
                  <li v-if="cardPeriod(e)[1]">
                    <svg aria-hidden="true" viewBox="0 0 24 24">
                      <circle cx="12" cy="12" r="9" />
                      <path d="M12 7v5l3 2" /></svg
                    >{{ cardPeriod(e)[1] }}
                  </li>
                  <li>
                    <svg aria-hidden="true" viewBox="0 0 24 24">
                      <path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z" />
                      <circle cx="12" cy="9" r="2.5" /></svg
                    >{{ e.lieu }}
                  </li>
                  <li>
                    <svg aria-hidden="true" viewBox="0 0 24 24">
                      <path d="M3 12V4h8l10 10-8 8z" />
                      <circle cx="7.5" cy="8.5" r="1.5" /></svg
                    >{{ e.type_evenement }}<template v-if="e.theme"> · Thème : {{ e.theme }}</template>
                  </li>
                </ul>
              </div>
            </article>
          </li>
        </ul>
      </template>
    </div>
  </main>
</template>
<style scoped>
/* Page Événements en style affiche : en-tête à photo, barre de filtres, cartes à bandeau de statut. */
.events-page {
  padding-inline: max(16px, calc((100% - 1200px) / 2));
}
.events-head {
  display: grid;
  grid-template-columns: auto minmax(240px, 1fr) minmax(0, 300px);
  gap: 24px 32px;
  align-items: center;
  padding-bottom: 24px;
  border-bottom: 2px solid var(--encre);
}
.events-head .poster-title {
  margin: 0;
  font-size: clamp(48px, 7vw, 96px);
  transform: rotate(-2deg) skew(-6deg);
}
.intro {
  margin: 0;
  padding-left: 20px;
  border-left: 2px solid var(--encre);
  font-family: Georgia, 'Times New Roman', serif;
  font-style: italic;
  font-size: 1.2rem;
  line-height: 1.4;
}
.head-photo {
  position: relative;
  aspect-ratio: 16 / 9;
}
.head-photo img {
  position: relative;
  width: 100%;
  height: 100%;
  object-fit: cover;
  filter: grayscale(1) contrast(1.1);
  clip-path: polygon(4% 6%, 100% 0, 97% 92%, 0 100%);
}
.head-photo .shape.red {
  inset: -14px 30% auto -18px;
  height: 45%;
  transform: rotate(-6deg);
}
.head-photo .shape.blue {
  inset: auto -12px -16px 55%;
  height: 40%;
  transform: rotate(-12deg);
}
/* Barre de filtres sur une ligne. */
.filter-bar {
  display: flex;
  flex-wrap: wrap;
  align-items: end;
  gap: 12px 20px;
  margin: 0;
  padding: 20px 0;
  border-bottom: 2px solid var(--encre);
}
.filter-bar > div:not(.filter-actions) {
  flex: 1 1 160px;
}
.filter-bar label {
  margin: 0 0 6px;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: 1.05rem;
}
.filter-actions {
  display: flex;
  gap: 12px;
  margin: 0;
}
.filter-actions button {
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: 1.15rem;
}
.filter-actions button[type='submit'] {
  background: var(--rouge);
  clip-path: polygon(0 6%, 100% 0, 97% 100%, 3% 94%);
}
.filter-actions button[type='submit']:hover {
  background: var(--rouge-fonce);
}
.filter-actions .secondary {
  background: var(--creme);
  border: 2px solid var(--encre);
}
.count {
  display: flex;
  align-items: center;
  gap: 24px;
  margin: 24px 0;
  font-family: var(--police-titre);
  font-size: 1.6rem;
  font-style: italic;
}
.count::after {
  content: '';
  flex: 1;
  border-top: 2px solid var(--encre);
}
/* Cartes : photo, bandeau de statut incliné, titre en Anton, liseré coloré en bas. */
.event-cards {
  display: flex;
  flex-wrap: wrap;
  gap: 28px;
}
/* Cartes souples : la dernière ligne s'élargit au lieu de laisser un vide. */
.event-cards > li {
  flex: 1 1 260px;
}
/* Zone de résultats : reçoit le focus par script pour l'annonce, sans contour visible. */
#events-result:focus {
  outline: none;
}
.event-cards article {
  position: relative;
  display: flex;
  flex-direction: column;
  border: 0;
  border-radius: 0;
  border-bottom: 5px solid var(--rouge);
  background: var(--creme);
  box-shadow: 0 2px 0 var(--filet);
  overflow: visible;
}
.event-cards li:nth-child(even) article {
  border-bottom-color: var(--bleu);
}
.event-cards article:focus-within {
  outline: 3px solid var(--encre);
  outline-offset: 3px;
}
.photo {
  position: relative;
  padding: 0;
  aspect-ratio: 16 / 10;
  background: var(--encre);
}
.photo img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  border-radius: 0;
}
.status {
  position: absolute;
  top: 12px;
  left: -6px;
  margin: 0;
  padding: 4px 14px;
  font-family: var(--police-titre);
  font-size: 1.1rem;
  text-transform: uppercase;
  color: white;
  background: var(--rouge);
  transform: rotate(-4deg);
}
.status.yellow {
  color: var(--encre);
  background: var(--jaune);
}
.status.blue {
  background: var(--bleu);
}
.status.grey {
  background: var(--texte-secondaire);
}
.body {
  padding: 18px 20px 20px;
}
.event-cards h2 {
  margin: 0 0 14px;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: 1.7rem;
  line-height: 1.05;
  text-transform: uppercase;
}
.event-cards h2 a {
  color: var(--encre);
  text-decoration: none;
}
.event-cards h2 a:hover {
  text-decoration: underline;
  text-decoration-color: var(--rouge);
}
/* Toute la carte est cliquable grâce au lien du titre, étendu à la carte. */
.event-cards h2 a::after {
  content: '';
  position: absolute;
  inset: 0;
}
.facts {
  margin: 0;
  padding: 0;
  list-style: none;
}
.facts li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  margin: 4px 0;
  font-size: 0.95rem;
  line-height: 1.4;
}
.facts svg {
  flex: none;
  width: 18px;
  height: 18px;
  margin-top: 2px;
  fill: none;
  stroke: var(--encre);
  stroke-width: 2;
}
@media (max-width: 900px) {
  .events-head {
    grid-template-columns: minmax(0, 1fr);
  }
  .head-photo {
    max-width: 420px;
  }
}
@media (max-width: 600px) {
  .head-photo {
    display: none;
  }
  .filter-bar > div:not(.filter-actions) {
    flex-basis: 100%;
  }
}
</style>
