<script setup>
// Modération des avis (équipe) : avis à modérer, publiés ou refusés ; validation ou refus de chaque avis.
import { nextTick, onMounted, ref } from 'vue'
import Icon from './Icon.vue'
import { auth, request } from './auth'

const tabs = [
  { key: 'en_attente', label: 'À modérer' },
  { key: 'valide', label: 'Publiés' },
  { key: 'refuse', label: 'Refusés' },
]

const status = ref('en_attente'),
  reviews = ref([]),
  loading = ref(true),
  error = ref(''),
  notice = ref(''),
  busy = ref('')
const day = v =>
  v ? new Date(v.replace(' ', 'T').slice(0, 19) + 'Z').toLocaleDateString('fr-FR', { timeZone: 'Europe/Paris' }) : ''

async function load() {
  loading.value = true
  error.value = ''
  try {
    reviews.value = (await request('/api/team/reviews?statut=' + status.value)).avis
  } catch {
    error.value = 'Les avis sont indisponibles pour le moment.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Change d'onglet et recharge la liste correspondante.
async function show(key) {
  status.value = key
  notice.value = ''
  await load()
}

/** Valide ou refuse un avis ; un avis déjà modéré par un collègue est signalé (409). */
async function decide(r, decision) {
  busy.value = r.id
  error.value = ''
  notice.value = ''
  let done = ''
  try {
    await request('/api/team/review-moderation', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify({ id: r.id, decision }),
    })
    done =
      decision === 'valide'
        ? `Avis de ${r.auteur} validé : il est publié sur la page Avis${r.evenement_public ? '' : ' dès que l’événement sera public'}.`
        : `Avis de ${r.auteur} refusé : il ne sera pas publié.`
  } catch (e) {
    error.value =
      e.status === 409
        ? 'Cet avis a déjà été modéré par un autre membre de l’équipe.'
        : e.status === 404
          ? 'Cet avis n’existe plus.'
          : e.status === 401 || e.status === 403
            ? 'Votre session a expiré. Reconnectez-vous.'
            : 'La décision n’a pas pu être confirmée. Rechargez la liste avant de réessayer.'
  }
  // Liste rechargée avant d'annoncer le résultat : le message correspond à ce qui est affiché.
  busy.value = ''
  await load()
  notice.value = done
  await nextTick()
  document.getElementById(error.value ? 'moderation-error' : 'moderation-notice')?.focus()
}
</script>
<template>
  <main class="work-page">
    <p><RouterLink class="back-link" to="/espace">← Retour à mon espace</RouterLink></p>
    <h1 class="list-title">Modération des avis</h1>
    <p>
      Seuls les avis validés apparaissent sur la <RouterLink to="/avis">page Avis</RouterLink>, et uniquement pour les
      événements dont le client a accepté la publication.
    </p>
    <!-- Onglets : à modérer, publiés, refusés -->
    <nav aria-label="Filtrer les avis" class="tabs">
      <button
        v-for="t in tabs"
        :key="t.key"
        type="button"
        :class="{ secondary: status !== t.key }"
        :aria-pressed="status === t.key"
        @click="show(t.key)"
      >
        {{ t.label }}
      </button>
    </nav>
    <p v-if="error" id="moderation-error" class="error" role="alert" tabindex="-1">{{ error }}</p>
    <p v-if="notice" id="moderation-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-else-if="!reviews.length">
      {{ status === 'en_attente' ? 'Aucun avis en attente : tout est à jour.' : 'Aucun avis dans cette liste.' }}
    </p>
    <!-- Avis de l'onglet choisi, avec validation ou refus -->
    <ul class="moderation-list">
      <li v-for="r in reviews" :key="r.id">
        <span class="result-tile yellow" aria-hidden="true"><Icon name="calendar" /></span>
        <div class="who">
          <h2 :id="'review-' + r.id">{{ r.evenement }}</h2>
          <p class="meta"><Icon name="user" />{{ r.auteur }}</p>
        </div>
        <p class="dates">
          <span><Icon name="calendar" />Déposé le {{ day(r.depose_le) }}</span
          ><span v-if="r.modere_le"
            ><span class="sr-only"> — </span
            ><svg aria-hidden="true" viewBox="0 0 24 24" class="ok">
              <circle cx="12" cy="12" r="9" />
              <path d="m8 12 3 3 5-6" /></svg
            >Modéré le {{ day(r.modere_le) }}</span
          >
        </p>
        <blockquote>« {{ r.contenu }} »</blockquote>
        <p v-if="!r.evenement_public" class="meta">
          Événement non public : même validé, l’avis n’apparaîtra pas sur le site tant que le client n’a pas accepté la
          publication.
        </p>
        <p v-if="r.statut === 'en_attente'" class="actions" role="group" :aria-labelledby="'review-' + r.id">
          <button type="button" :disabled="!!busy" @click="decide(r, 'valide')">
            {{ busy === r.id ? 'Enregistrement…' : 'Valider et publier' }}
          </button>
          <button type="button" class="secondary" :disabled="!!busy" @click="decide(r, 'refuse')">Refuser</button>
        </p>
      </li>
    </ul>
  </main>
</template>
<style scoped>
/* Modération : onglets, puis un avis par carte (événement, dates, citation, actions). */
.tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  margin: 24px 0 20px;
  padding-bottom: 0;
  border-bottom: 2px solid var(--nuit);
}
.tabs button {
  margin-bottom: -2px;
  border-radius: 8px 8px 0 0;
  border: 2px solid var(--nuit);
  color: white;
  background: var(--nuit);
}
.work-page .tabs button:not(.secondary) {
  background: var(--nuit);
  border-radius: 8px 8px 0 0;
  font-size: 1rem;
  padding-inline: 24px;
}
.tabs button.secondary {
  color: var(--encre);
  background: var(--creme);
}
.moderation-list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.moderation-list li {
  display: grid;
  grid-template-columns: auto minmax(0, 1.1fr) auto minmax(0, 1.6fr) auto;
  gap: 10px 20px;
  align-items: center;
  margin: 0 0 14px;
  padding: 16px 20px;
  background: var(--creme);
  border: 1px solid var(--filet);
  border-radius: 10px;
}
.moderation-list li > .meta,
.moderation-list li > .actions {
  grid-column: 1 / -1;
}
h2 {
  margin: 0 0 4px;
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1.15rem;
  overflow-wrap: anywhere;
}
.meta {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
}
.meta :deep(.icon),
.dates :deep(.icon) {
  width: 18px;
  height: 18px;
}
.dates {
  display: flex;
  flex-direction: column;
  gap: 6px;
  margin: 0;
  padding: 0 20px;
  border-inline: 1px solid var(--filet);
  font-size: 0.95rem;
  white-space: nowrap;
}
.ok {
  flex: none;
  width: 18px;
  height: 18px;
  fill: none;
  stroke: var(--succes);
  stroke-width: 1.8;
}
.dates > span {
  display: flex;
  align-items: center;
  gap: 8px;
}
blockquote {
  margin: 0;
  font-family: Georgia, 'Times New Roman', serif;
  font-style: italic;
  overflow-wrap: anywhere;
}
.actions {
  gap: 10px;
  margin: 0;
}
.actions button {
  border-radius: 6px;
}
.actions .secondary {
  color: var(--rouge);
  background: var(--carte);
  border: 2px solid var(--rouge);
}
@media (max-width: 1000px) {
  .moderation-list li {
    grid-template-columns: auto minmax(0, 1fr);
  }
  .moderation-list li > .dates,
  .moderation-list li > blockquote {
    grid-column: 1 / -1;
  }
  .dates {
    padding: 0;
    border: 0;
    white-space: normal;
  }
}
</style>
