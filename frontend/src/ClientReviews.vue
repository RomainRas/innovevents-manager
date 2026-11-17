<script setup>
// Bloc « Vos avis » de l'espace client : un avis par événement terminé, publié après validation par l'équipe.
import { nextTick, onMounted, ref } from 'vue'
import { auth, request } from './auth'

const events = ref([]),
  loading = ref(true),
  error = ref(''),
  notice = ref(''),
  open = ref(''),
  text = ref(''),
  fieldError = ref(''),
  busy = ref(false)

// État de l'avis affiché au client.
const labels = {
  en_attente: 'En attente de validation par l’équipe',
  valide: 'Publié sur la page Avis',
  refuse: 'Non publié',
}

// Date locale « AAAA-MM-JJTHH:MM » → « JJ/MM/AAAA ».
const local = v => {
  const m = /^(\d{4})-(\d{2})-(\d{2})T/.exec(v || '')
  return m ? `${m[3]}/${m[2]}/${m[1]}` : ''
}

async function load() {
  try {
    events.value = (await request('/api/me/reviews')).evenements
  } catch {
    error.value = 'Vos événements terminés sont indisponibles pour le moment.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Ouvre le formulaire d'avis d'un événement ; annuler rend le focus au bouton d'origine.
async function start(e) {
  open.value = e.id
  text.value = ''
  fieldError.value = ''
  notice.value = ''
  await nextTick()
  document.getElementById('review-text')?.focus()
}

async function cancel(e) {
  open.value = ''
  await nextTick()
  document.getElementById('review-start-' + e.id)?.focus()
}

/** Envoie l'avis (10 à 2 000 caractères, contrôlés aussi par l'API) ; la liste renvoyée remplace l'affichage. */
async function submit(e) {
  fieldError.value = ''
  error.value = ''
  if (text.value.trim().length < 10) {
    fieldError.value = 'Rédigez votre avis (entre 10 et 2 000 caractères).'
    await nextTick()
    document.getElementById('review-text')?.focus()
    return
  }
  busy.value = true
  try {
    events.value = (
      await request('/api/me/review', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
        body: JSON.stringify({ evenement_id: e.id, contenu: text.value }),
      })
    ).evenements
    open.value = ''
    notice.value = 'Merci ! Votre avis sera publié après validation par l’équipe Innov’Events.'
    busy.value = false
    await nextTick()
    document.getElementById('reviews-notice')?.focus()
  } catch (x) {
    busy.value = false
    if (x.status === 422) {
      fieldError.value = x.fields?.contenu || 'Avis invalide.'
      await nextTick()
      document.getElementById('review-text')?.focus()
      return
    }
    if (x.status === 409) {
      open.value = ''
      await load()
      notice.value = 'Un avis a déjà été donné pour cet événement.'
    } else
      error.value =
        x.status === 401 || x.status === 403
          ? 'Votre session a expiré. Reconnectez-vous pour envoyer votre avis.'
          : 'L’envoi n’a pas pu être confirmé. Votre texte est conservé : réessayez.'
    await nextTick()
    document.getElementById(error.value ? 'reviews-error' : 'reviews-notice')?.focus()
  }
}
</script>
<template>
  <section aria-labelledby="reviews-title">
    <div class="block-head bar-yellow">
      <span class="block-icon" aria-hidden="true"
        ><svg viewBox="0 0 24 24">
          <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z" /></svg
      ></span>
      <h2 id="reviews-title">Évaluer mes événements terminés</h2>
    </div>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" id="reviews-error" class="error" role="alert" tabindex="-1">{{ error }}</p>
    <p v-if="notice" id="reviews-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
    <p v-if="!loading && !error && !events.length" class="empty-box">
      Aucun événement terminé pour le moment. Vous pourrez l’évaluer une fois celui-ci passé.
    </p>
    <!-- Un élément par événement terminé : avis déjà donné ou formulaire -->
    <ul v-if="events.length" class="review-list row-list">
      <li v-for="(e, i) in events" :key="e.id" class="review-item" :class="['red', 'blue', 'yellow'][i % 3]">
        <div class="review-event">
          <h3 :id="'review-event-' + e.id">{{ e.nom }}</h3>
          <p class="meta">
            {{ local(e.debut) }}<template v-if="e.lieu"> — {{ e.lieu }}</template>
          </p>
        </div>
        <template v-if="e.avis">
          <blockquote>« {{ e.avis.contenu }} »</blockquote>
          <p class="review-status" :class="e.avis.statut">
            <svg v-if="e.avis.statut === 'valide'" aria-hidden="true" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="9" />
              <path d="m8 12 3 3 5-6" /></svg
            ><strong>{{ labels[e.avis.statut] || e.avis.statut }}</strong>
          </p>
        </template>
        <button
          v-else-if="open !== e.id"
          :id="'review-start-' + e.id"
          type="button"
          class="give"
          :aria-describedby="'review-event-' + e.id"
          @click="start(e)"
        >
          <svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 5h16v11H9l-5 4z" /></svg>Donner mon avis
        </button>
        <form v-else novalidate :aria-busy="busy" @submit.prevent="submit(e)">
          <fieldset :disabled="busy">
            <legend class="sr-only">Votre avis sur {{ e.nom }}</legend>
            <label for="review-text">Votre avis sur « {{ e.nom }} »</label>
            <textarea
              id="review-text"
              v-model="text"
              rows="5"
              maxlength="2000"
              required
              :aria-invalid="!!fieldError"
              :aria-describedby="fieldError ? 'review-text-error' : 'review-text-help'"
            ></textarea>
            <p v-if="fieldError" id="review-text-error" class="error">{{ fieldError }}</p>
            <p v-else id="review-text-help" class="meta">
              Votre prénom et l’initiale de votre nom accompagneront l’avis s’il est publié.
            </p>
            <p class="actions">
              <button type="submit">{{ busy ? 'Envoi…' : 'Envoyer mon avis' }}</button
              ><button type="button" class="secondary" @click="cancel(e)">Annuler</button>
            </p>
          </fieldset>
        </form>
      </li>
    </ul>
  </section>
</template>
<style scoped>
/* Un événement par ligne : liseré coloré, nom et date, citation, statut ou bouton d'avis. */
.review-item {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 1.4fr) auto;
  gap: 8px 24px;
  align-items: center;
  padding-left: 32px !important;
}
.review-item::before {
  content: '';
  position: absolute;
  top: 14px;
  bottom: 14px;
  left: 14px;
  width: 4px;
  border-radius: 2px;
  background: var(--rouge);
}
.review-item.blue::before {
  background: var(--bleu);
}
.review-item.yellow::before {
  background: var(--jaune);
}
.review-item form {
  grid-column: 1 / -1;
}
h3 {
  margin: 0 0 4px;
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1.2rem;
  overflow-wrap: anywhere;
}
.review-event .meta {
  margin: 0;
}
blockquote {
  margin: 0;
  font-family: Georgia, 'Times New Roman', serif;
  font-style: italic;
  overflow-wrap: anywhere;
}
.review-status {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  padding-left: 24px;
  border-left: 1px solid var(--filet);
}
.review-status strong {
  font-weight: 400;
}
.review-status svg,
.give svg {
  flex: none;
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.review-status.valide svg {
  color: var(--succes);
}
.give {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  grid-column: 3;
  border-radius: 6px;
  background: var(--rouge);
}
.give:hover {
  background: var(--rouge-fonce);
}
textarea {
  border: 1px solid var(--bordure-champ);
  border-radius: 6px;
  background: var(--carte);
}
@media (max-width: 900px) {
  .review-item {
    grid-template-columns: minmax(0, 1fr);
  }
  .give {
    grid-column: 1;
    justify-self: start;
  }
  .review-status {
    padding-left: 0;
    border-left: 0;
  }
}
</style>
