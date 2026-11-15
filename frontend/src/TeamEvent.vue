<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { auth, request } from './auth'
import { eventStatuses, localDate, stamp, taskStatuses } from './teamFormat'
import AdminEventPanel from './AdminEventPanel.vue'
const route = useRoute(), data = ref(null), error = ref(''), loading = ref(true)
const draft = ref(''), draftError = ref(''), editing = ref(''), editText = ref(''), editError = ref(''), confirmDelete = ref(''), busy = ref(''), notice = ref(''), actionError = ref('')
const nextLabels = { en_cours: 'Commencer la tâche', termine: 'Marquer comme terminée' }
onMounted(async () => {
  try { data.value = await request('/api/team/event?id=' + encodeURIComponent(route.params.id)) }
  catch (e) { error.value = e.status === 404 || e.status === 422 ? 'Cet événement n’existe pas.' : 'La fiche événement est indisponible pour le moment.' }
  finally { loading.value = false }
  if (route.query.message === 'cree') notice.value = 'Événement créé (brouillon).'
  else if (route.query.message === 'modifie') notice.value = 'Événement modifié.'
})
const isAdmin = () => auth.user?.role === 'administrateur'
async function deleteTask(t) {
  busy.value = 'task-' + t.id; notice.value = ''; actionError.value = ''
  let done = false
  try { await post('/api/admin/task-delete', { id: t.id }); done = true }
  catch (e) { actionError.value = fail(e, 'La suppression de la tâche n’a pas pu être confirmée. Réessayez.') }
  // Liste rechargée avant d'annoncer le résultat : le message correspond à ce qui est affiché.
  await reload(); if (done) notice.value = `Tâche « ${t.titre} » supprimée.`
  busy.value = ''; await focus(actionError.value ? 'team-error' : 'team-notice')
}
const post = (path, body) => request(path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf }, body: JSON.stringify(body) })
async function focus(id) { await nextTick(); document.getElementById(id)?.focus() }
function fail(e, fallback) {
  return e.status === 401 ? 'Votre session a expiré. Reconnectez-vous.' : e.status === 403 ? 'Vous ne pouvez pas modifier cet élément.'
    : e.status === 404 ? 'Cet élément n’existe plus : la fiche a été rechargée.' : e.status === 409 ? 'Cet élément a été modifié entre-temps : la fiche a été rechargée.' : fallback
}
async function reload() { try { data.value = await request('/api/team/event?id=' + encodeURIComponent(route.params.id)) } catch { /* La fiche affichée reste visible. */ } }
async function addNote() {
  draftError.value = ''; notice.value = ''; actionError.value = ''
  if (!draft.value.trim()) { draftError.value = 'Rédigez la note avant de l’ajouter.'; return focus('note-new') }
  busy.value = 'new'
  try { data.value = await post('/api/team/note-save', { evenement_id: data.value.evenement.id, contenu: draft.value }); draft.value = ''; notice.value = 'Note ajoutée.'; busy.value = ''; await focus('team-notice') }
  catch (e) { busy.value = ''; if (e.status === 422) { draftError.value = e.fields?.contenu || 'Note invalide.'; await focus('note-new') } else { actionError.value = fail(e, 'La note n’a pas pu être enregistrée. Votre texte est conservé : réessayez.'); await focus('team-error') } }
  finally { busy.value = '' }
}
async function startEdit(n) { editing.value = n.id; editText.value = n.contenu; editError.value = ''; confirmDelete.value = ''; await focus('note-edit-' + n.id) }
async function cancelEdit(n) { editing.value = ''; await focus('note-actions-' + n.id) }
async function saveEdit(n) {
  editError.value = ''; notice.value = ''; actionError.value = ''
  if (!editText.value.trim()) { editError.value = 'La note ne peut pas être vide.'; return focus('note-edit-' + n.id) }
  busy.value = n.id
  try { data.value = await post('/api/team/note-save', { id: n.id, contenu: editText.value }); editing.value = ''; notice.value = 'Note modifiée.'; busy.value = ''; await focus('team-notice') }
  catch (e) { busy.value = ''; if (e.status === 422) { editError.value = e.fields?.contenu || 'Note invalide.'; await focus('note-edit-' + n.id) } else { actionError.value = fail(e, 'La modification n’a pas pu être enregistrée. Réessayez.'); editing.value = ''; await reload(); await focus('team-error') } }
  finally { busy.value = '' }
}
async function askDelete(n) { confirmDelete.value = n.id; editing.value = ''; await focus('note-delete-' + n.id) }
async function cancelDelete(n) { confirmDelete.value = ''; await focus('note-actions-' + n.id) }
async function deleteNote(n) {
  busy.value = n.id; notice.value = ''; actionError.value = ''
  try { data.value = await post('/api/team/note-delete', { id: n.id }); notice.value = 'Note supprimée.' }
  catch (e) { actionError.value = fail(e, 'La suppression n’a pas pu être confirmée. Réessayez.'); await reload() }
  confirmDelete.value = ''; busy.value = ''; await focus(actionError.value ? 'team-error' : 'team-notice')
}
async function advance(t) {
  busy.value = 'task-' + t.id; notice.value = ''; actionError.value = ''
  try { data.value = await post('/api/team/task-status', { id: t.id, statut: t.suivant }); notice.value = `Tâche « ${t.titre} » : ${taskStatuses[t.suivant].toLowerCase()}.` }
  catch (e) { actionError.value = fail(e, 'Le changement de statut n’a pas pu être confirmé. Réessayez.'); await reload() }
  busy.value = ''; await focus(actionError.value ? 'team-error' : 'team-notice')
}
</script>
<template>
  <main class="panel wide">
    <p><RouterLink to="/equipe/evenements">← Tous les événements</RouterLink></p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <template v-if="data">
      <h1>{{ data.evenement.nom }}</h1>
      <dl class="facts">
        <div><dt>Statut</dt><dd>{{ eventStatuses[data.evenement.statut] || data.evenement.statut }}</dd></div>
        <div><dt>Début</dt><dd>{{ localDate(data.evenement.debut) }}</dd></div>
        <div><dt>Fin</dt><dd>{{ localDate(data.evenement.fin) }}</dd></div>
        <div><dt>Lieu</dt><dd>{{ data.evenement.lieu || 'À définir' }}</dd></div>
        <div><dt>Type et thème</dt><dd>{{ data.evenement.type_evenement || '—' }}<template v-if="data.evenement.theme"> — {{ data.evenement.theme }}</template></dd></div>
        <div><dt>Participants</dt><dd>{{ data.evenement.participants ?? '—' }}</dd></div>
        <div><dt>Client</dt><dd><RouterLink :to="'/equipe/clients/' + data.evenement.client_id">{{ data.evenement.client }}</RouterLink><br>{{ data.evenement.contact }}<br><a :href="'tel:' + data.evenement.telephone">{{ data.evenement.telephone }}</a></dd></div>
      </dl>
      <p v-if="actionError" id="team-error" class="error" role="alert" tabindex="-1">{{ actionError }}</p>
      <p v-if="notice" id="team-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>

      <AdminEventPanel v-if="isAdmin()" :event-id="data.evenement.id" @changed="reload" />

      <section aria-labelledby="tasks-title">
        <h2 id="tasks-title">Tâches</h2>
        <p v-if="!data.taches.length">Aucune tâche pour cet événement.</p>
        <ul class="list">
          <li v-for="t in data.taches" :key="t.id" :class="{ mine: t.a_moi }">
            <h3 :id="'task-' + t.id">{{ t.titre }} <span class="badge" :class="t.statut">{{ taskStatuses[t.statut] }}</span></h3>
            <p v-if="t.description" class="text">{{ t.description }}</p>
            <p class="meta">{{ t.assigne ? 'Assignée à ' + t.assigne : 'Non assignée' }}<template v-if="t.a_moi"> (vous)</template></p>
            <p class="actions">
              <button v-if="t.suivant" type="button" :disabled="!!busy" :aria-describedby="'task-' + t.id" @click="advance(t)">{{ busy === 'task-' + t.id ? 'Enregistrement…' : nextLabels[t.suivant] }}</button>
              <button v-if="isAdmin()" type="button" class="secondary" :disabled="!!busy" :aria-describedby="'task-' + t.id" @click="deleteTask(t)">Supprimer la tâche</button>
            </p>
          </li>
        </ul>
      </section>

      <section aria-labelledby="notes-title">
        <h2 id="notes-title">Notes de l’équipe</h2>
        <form novalidate @submit.prevent="addNote">
          <label for="note-new">Ajouter une note</label>
          <textarea id="note-new" v-model="draft" rows="3" maxlength="5000" :disabled="busy === 'new'" :aria-invalid="!!draftError" :aria-describedby="draftError ? 'note-new-error' : undefined"></textarea>
          <p v-if="draftError" id="note-new-error" class="error">{{ draftError }}</p>
          <button type="submit" :disabled="!!busy">{{ busy === 'new' ? 'Ajout…' : 'Ajouter la note' }}</button>
        </form>
        <p v-if="!data.notes.length">Aucune note pour le moment.</p>
        <ul class="list">
          <li v-for="n in data.notes" :key="n.id">
            <p class="meta">{{ n.auteur }} — {{ stamp(n.cree_le) }}<template v-if="n.modifiee"> (modifiée)</template></p>
            <template v-if="editing === n.id">
              <label :for="'note-edit-' + n.id">Modifier la note</label>
              <textarea :id="'note-edit-' + n.id" v-model="editText" rows="4" maxlength="5000" :aria-invalid="!!editError" :aria-describedby="editError ? 'note-edit-error' : undefined"></textarea>
              <p v-if="editError" id="note-edit-error" class="error">{{ editError }}</p>
              <p class="actions"><button type="button" :disabled="!!busy" @click="saveEdit(n)">{{ busy === n.id ? 'Enregistrement…' : 'Enregistrer' }}</button><button type="button" class="secondary" :disabled="!!busy" @click="cancelEdit(n)">Annuler</button></p>
            </template>
            <template v-else>
              <p class="text">{{ n.contenu }}</p>
              <div v-if="confirmDelete === n.id" :id="'note-delete-' + n.id" class="dialog" role="group" tabindex="-1" :aria-label="'Confirmer la suppression de la note de ' + n.auteur">
                <p>Supprimer définitivement cette note ?</p>
                <p class="actions"><button type="button" class="danger-button" :disabled="!!busy" @click="deleteNote(n)">{{ busy === n.id ? 'Suppression…' : 'Supprimer la note' }}</button><button type="button" class="secondary" :disabled="!!busy" @click="cancelDelete(n)">Annuler</button></p>
              </div>
              <p v-else-if="n.modifiable" :id="'note-actions-' + n.id" class="actions" tabindex="-1">
                <button type="button" class="secondary" :disabled="!!busy" @click="startEdit(n)">Modifier</button>
                <button type="button" class="secondary" :disabled="!!busy" @click="askDelete(n)">Supprimer…</button>
              </p>
            </template>
          </li>
        </ul>
      </section>
    </template>
  </main>
</template>
<style scoped>
.facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin: 16px 0 }
.facts div { background: #EEF3F6; border-radius: 6px; padding: 12px }
dt { font-size: .9rem; color: #2F4250 } dd { margin: 4px 0 0; font-weight: 600; overflow-wrap: anywhere }
.list { list-style: none; padding: 0 }
.list li { border: 1px solid #CBD5E1; border-radius: 8px; padding: 16px; margin: 12px 0 }
.list li.mine { border-left: 4px solid #087F8C }
h3 { margin: 0 0 4px; font-size: 1.1rem; overflow-wrap: anywhere }
.badge { display: inline-block; margin-left: 4px; padding: 2px 8px; border-radius: 12px; background: #E6EEF3; font-size: .85rem; font-weight: 600 }
.badge.en_cours { background: #FFF4D6; color: #6B4E00 } .badge.termine { background: #E3F4EA; color: #0B6B3A }
.text { white-space: pre-line; overflow-wrap: anywhere }
.meta { color: #4A5B68; font-size: .95rem; margin: 4px 0 }
.actions { display: flex; flex-wrap: wrap; gap: 12px }
form button { margin-top: 12px }
.dialog { border: 2px solid #B42318; border-radius: 8px; padding: 12px }
.danger-button { background: #B42318 } .danger-button:hover { background: #8F1C13 }
.success { color: #0B6B3A; font-weight: 600 }
</style>
