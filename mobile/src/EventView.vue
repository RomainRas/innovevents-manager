<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { api, draft, post, state, statuses, when } from './api'
const props = defineProps({ id: { type: String, required: true } })
const data = ref(null), loading = ref(true), error = ref('')
const sheet = ref(false), text = ref(''), noteError = ref(''), saving = ref(false), notice = ref('')
async function load() {
  try { data.value = await api('/api/mobile/event?id=' + props.id) }
  catch (e) {
    if (e.status === 401) { state.user = null; return }
    error.value = e.status === 404 ? 'Cet événement n’existe plus.' : e.status === 503 || e.status === 0 ? 'Pas de connexion et cette fiche n’a pas encore été consultée sur ce téléphone.' : 'La fiche est indisponible pour le moment.'
  } finally { loading.value = false }
}
onMounted(load)
async function openSheet() {
  sheet.value = true; notice.value = ''; noteError.value = ''; text.value = draft.get(props.id)
  await nextTick(); document.getElementById('note-text')?.focus()
}
async function closeSheet() { sheet.value = false; draft.set(props.id, text.value.trim() ? text.value : ''); await nextTick(); document.getElementById('add-note')?.focus() }
async function save() {
  noteError.value = ''
  if (!text.value.trim()) { noteError.value = 'Saisissez la note avant de l’enregistrer.'; document.getElementById('note-text')?.focus(); return }
  saving.value = true
  try {
    data.value = await post('/api/mobile/note', { evenement_id: props.id, contenu: text.value })
    draft.set(props.id, ''); text.value = ''; sheet.value = false; notice.value = 'Note enregistrée.'
    saving.value = false; await nextTick(); document.getElementById('note-notice')?.focus()
  } catch (e) {
    // Le texte est conservé sur le téléphone pour ne rien perdre « à chaud ».
    draft.set(props.id, text.value)
    noteError.value = e.status === 0 ? 'Pas de connexion : la note est gardée sur ce téléphone. Réessayez dès que le réseau revient.'
      : e.status === 422 ? (e.fields?.contenu || 'Note invalide.') : e.status === 401 || e.status === 403 ? 'Votre session a expiré : reconnectez-vous, la note est gardée.' : 'La note n’a pas pu être enregistrée. Elle est gardée sur ce téléphone : réessayez.'
    saving.value = false; await nextTick(); document.getElementById('note-text')?.focus()
  }
}
</script>
<template>
  <main class="page">
    <a class="back" href="#/">← Événements à venir</a>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <template v-if="data">
      <h1 tabindex="-1">{{ data.evenement.nom }}</h1>
      <span class="badge" :class="data.evenement.statut">{{ statuses[data.evenement.statut] || data.evenement.statut }}</span>
      <dl class="facts">
        <div><dt>Dates</dt><dd>{{ when(data.evenement.debut, data.evenement.fin) }}</dd></div>
        <div><dt>Lieu</dt><dd>{{ data.evenement.lieu || 'À définir' }}</dd></div>
        <div><dt>Client</dt><dd><a class="client-link" :href="`#/client/${data.evenement.client.id}?evenement=${data.evenement.id}`">{{ data.evenement.client.entreprise }}<span class="sub">{{ data.evenement.client.contact }} — appeler, écrire, itinéraire</span></a></dd></div>
      </dl>
      <button id="add-note" type="button" class="primary wide big" :disabled="state.offline && !sheet" @click="openSheet">＋ Ajouter une note</button>
      <p v-if="state.offline" class="hint">L’ajout de note nécessite le réseau.</p>
      <p v-if="notice" id="note-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
      <div v-if="sheet" class="sheet" role="dialog" aria-modal="false" aria-labelledby="note-title">
        <h2 id="note-title">Nouvelle note</h2>
        <label for="note-text">Note sur « {{ data.evenement.nom }} »</label>
        <textarea id="note-text" v-model="text" rows="6" maxlength="5000" enterkeyhint="done" :aria-invalid="!!noteError" :aria-describedby="noteError ? 'note-error' : undefined"></textarea>
        <p v-if="noteError" id="note-error" class="error" role="alert">{{ noteError }}</p>
        <div class="row"><button type="button" class="primary" :disabled="saving" @click="save">{{ saving ? 'Enregistrement…' : 'Enregistrer' }}</button><button type="button" class="secondary" :disabled="saving" @click="closeSheet">Fermer</button></div>
      </div>
      <h2>Dernières notes</h2>
      <p v-if="!data.notes.length">Aucune note pour cet événement.</p>
      <ul class="notes"><li v-for="n in data.notes" :key="n.id"><span class="meta">{{ n.auteur }} — {{ new Date(n.cree_le.replace(' ', 'T').slice(0, 19) + 'Z').toLocaleString('fr-FR', { timeZone: 'Europe/Paris', dateStyle: 'short', timeStyle: 'short' }) }}</span><p>{{ n.contenu }}</p></li></ul>
    </template>
  </main>
</template>
