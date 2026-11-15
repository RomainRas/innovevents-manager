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
    <h2 id="reviews-title">Évaluer mes événements terminés</h2>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" id="reviews-error" class="error" role="alert" tabindex="-1">{{ error }}</p>
    <p v-if="notice" id="reviews-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
    <p v-if="!loading && !error && !events.length">
      Aucun événement terminé pour le moment. Vous pourrez l’évaluer une fois celui-ci passé.
    </p>
    <!-- Un élément par événement terminé : avis déjà donné ou formulaire -->
    <ul class="review-list">
      <li v-for="e in events" :key="e.id" class="review-item">
        <h3 :id="'review-event-' + e.id">{{ e.nom }}</h3>
        <p class="meta">
          {{ local(e.debut) }}<template v-if="e.lieu"> — {{ e.lieu }}</template>
        </p>
        <template v-if="e.avis">
          <blockquote>« {{ e.avis.contenu }} »</blockquote>
          <p>
            <strong>{{ labels[e.avis.statut] || e.avis.statut }}</strong>
          </p>
        </template>
        <button
          v-else-if="open !== e.id"
          :id="'review-start-' + e.id"
          type="button"
          class="secondary"
          :aria-describedby="'review-event-' + e.id"
          @click="start(e)"
        >
          Donner mon avis
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
.review-list {
  list-style: none;
  padding: 0;
}
.review-item {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 16px;
  margin: 16px 0;
}
h3 {
  margin: 0 0 4px;
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
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
</style>
