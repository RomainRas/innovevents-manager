<script setup>
// Modération des avis (équipe) : avis à modérer, publiés ou refusés ; validation ou refus de chaque avis.
import { nextTick, onMounted, ref } from 'vue'
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
  <main class="panel">
    <p><RouterLink to="/espace">← Retour à mon espace</RouterLink></p>
    <h1>Modération des avis</h1>
    <p>
      Seuls les avis validés apparaissent sur la <RouterLink to="/avis">page Avis</RouterLink>, et uniquement pour les
      événements dont le client a accepté la publication.
    </p>
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
    <ul class="moderation-list">
      <li v-for="r in reviews" :key="r.id">
        <h2 :id="'review-' + r.id">{{ r.evenement }}</h2>
        <p class="meta">
          {{ r.auteur }} — déposé le {{ day(r.depose_le)
          }}<template v-if="r.modere_le"> — modéré le {{ day(r.modere_le) }}</template>
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
.tabs {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 16px 0;
}
.moderation-list {
  list-style: none;
  padding: 0;
}
.moderation-list li {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 16px;
  margin: 16px 0;
}
h2 {
  margin: 0 0 4px;
  font-size: 1.2rem;
  overflow-wrap: anywhere;
}
blockquote {
  margin: 12px 0;
  padding-left: 12px;
  border-left: 3px solid #087f8c;
  overflow-wrap: anywhere;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.success {
  color: #0b6b3a;
  font-weight: 600;
}
</style>
