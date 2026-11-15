<script setup>
// Gestion de l'événement par l'administratrice : statut (journalisé), modification, tâches, suppression.
import { nextTick, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, request } from './auth'
import { eventStatuses } from './teamFormat'

const props = defineProps({ eventId: { type: String, required: true } })
const emit = defineEmits(['changed'])
const router = useRouter(),
  info = ref(null),
  staff = ref([]),
  loadError = ref('')
const status = ref(''),
  busy = ref(''),
  notice = ref(''),
  error = ref('')
const task = reactive({ titre: '', description: '', assigne_a: '' }),
  taskErrors = ref({}),
  confirmDelete = ref(false)

// Statuts proposés au changement, et messages des refus de l'API.
const targets = ['accepte', 'en_cours', 'termine', 'annule']
const statusErrors = {
  event_cancelled: 'Cet événement est annulé : son statut ne peut plus changer.',
  status_unchanged: 'L’événement a déjà ce statut.',
  event_incomplete: 'Renseignez le nom, le début et la fin avant de changer le statut.',
}

/** Charge l'événement et la liste de l'équipe (pour assigner une tâche). */
async function load() {
  try {
    const d = await request('/api/admin/event-edit?id=' + props.eventId)
    info.value = d.evenement
    staff.value = d.equipe
    status.value = ''
  } catch {
    loadError.value = 'La gestion de l’événement est indisponible pour le moment.'
  }
}

onMounted(load)

// Écriture JSON vers l'API avec le jeton CSRF.
const post = (path, body) =>
  request(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
    body: JSON.stringify(body),
  })

// Place le focus sur un élément après la mise à jour de l'affichage.
async function focus(id) {
  await nextTick()
  document.getElementById(id)?.focus()
}

/** Change le statut de l'événement ; chaque changement est enregistré dans le journal. */
async function changeStatus() {
  error.value = ''
  notice.value = ''
  if (!status.value) {
    error.value = 'Choisissez le nouveau statut.'
    return focus('admin-status')
  }
  busy.value = 'status'
  try {
    const d = await post('/api/admin/event-status', { id: props.eventId, statut: status.value })
    info.value = d.evenement
    notice.value = `Statut changé : ${eventStatuses[d.evenement.statut]}. Le changement est enregistré dans le journal.`
    status.value = ''
    emit('changed')
  } catch (e) {
    error.value = statusErrors[e.message] || 'Le changement n’a pas pu être confirmé. Réessayez.'
  }
  busy.value = ''
  await focus(error.value ? 'admin-error' : 'admin-notice')
}

/** Ajoute une tâche à l'événement, éventuellement assignée à un membre de l'équipe. */
async function addTask() {
  taskErrors.value = {}
  error.value = ''
  notice.value = ''
  if (!task.titre.trim()) {
    taskErrors.value = { titre: 'Indiquez le titre de la tâche.' }
    return focus('task-titre')
  }
  busy.value = 'task'
  try {
    await post('/api/admin/task-create', { evenement_id: props.eventId, ...task })
    notice.value = `Tâche « ${task.titre.trim()} » ajoutée.`
    Object.assign(task, { titre: '', description: '', assigne_a: '' })
    emit('changed')
  } catch (e) {
    taskErrors.value = e.fields || {}
    error.value =
      e.status === 409
        ? 'Cet événement est annulé : aucune tâche ne peut être ajoutée.'
        : e.status === 422
          ? 'Vérifiez la tâche.'
          : 'La tâche n’a pas pu être enregistrée. Réessayez.'
  }
  busy.value = ''
  await focus(
    Object.keys(taskErrors.value)[0]
      ? 'task-' + Object.keys(taskErrors.value)[0]
      : error.value
        ? 'admin-error'
        : 'admin-notice',
  )
}

// Suppression en deux temps : demande, puis confirmation (le focus revient au bouton si on annule).
async function askDelete() {
  confirmDelete.value = true
  notice.value = ''
  error.value = ''
  await focus('admin-delete-dialog')
}

async function cancelDelete() {
  confirmDelete.value = false
  await focus('admin-delete-button')
}

/** Supprime l'événement ; un événement qui a un devis est refusé (historique commercial à garder). */
async function remove() {
  busy.value = 'delete'
  try {
    await post('/api/admin/event-delete', { id: props.eventId, confirmation: true })
    await router.replace({ path: '/equipe/evenements', query: { message: 'supprime' } })
  } catch (e) {
    error.value =
      e.message === 'event_has_quote'
        ? 'Cet événement a un devis : il ne peut pas être supprimé. Annulez-le plutôt.'
        : 'La suppression n’a pas pu être confirmée. Réessayez.'
    confirmDelete.value = false
    busy.value = ''
    await focus('admin-error')
  }
}
</script>
<template>
  <section class="admin" aria-labelledby="admin-title">
    <h2 id="admin-title">Gestion de l’événement</h2>
    <p v-if="loadError" class="error" role="alert">{{ loadError }}</p>
    <template v-if="info">
      <p v-if="error" id="admin-error" class="error" role="alert" tabindex="-1">{{ error }}</p>
      <p v-if="notice" id="admin-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
      <template v-if="info.statut !== 'annule'">
        <div class="row">
          <div>
            <label for="admin-status">Nouveau statut</label>
            <select id="admin-status" v-model="status" :disabled="!!busy">
              <option value="">— Choisir —</option>
              <option v-for="s in targets.filter(s => s !== info.statut)" :key="s" :value="s">
                {{ eventStatuses[s] }}
              </option>
            </select>
          </div>
          <button type="button" :disabled="!!busy" @click="changeStatus">
            {{ busy === 'status' ? 'Enregistrement…' : 'Changer le statut' }}
          </button>
        </div>
        <p class="meta">« Annulé » est définitif : l’événement ne pourra plus être modifié.</p>
        <p class="actions">
          <RouterLink class="button-link" :to="'/equipe/evenements/' + eventId + '/modifier'"
            >Modifier les informations</RouterLink
          >
        </p>
        <form class="task" novalidate @submit.prevent="addTask">
          <h3>Ajouter une tâche</h3>
          <fieldset :disabled="!!busy">
            <legend class="sr-only">Nouvelle tâche</legend>
            <label for="task-titre">Titre *</label>
            <input
              id="task-titre"
              v-model="task.titre"
              maxlength="200"
              :aria-invalid="!!taskErrors.titre"
              :aria-describedby="taskErrors.titre ? 'task-err-titre' : undefined"
            />
            <p v-if="taskErrors.titre" id="task-err-titre" class="error">{{ taskErrors.titre }}</p>
            <label for="task-description">Description</label>
            <textarea id="task-description" v-model="task.description" rows="2" maxlength="2000"></textarea>
            <label for="task-assigne_a">Assignée à</label>
            <select
              id="task-assigne_a"
              v-model="task.assigne_a"
              :aria-invalid="!!taskErrors.assigne_a"
              :aria-describedby="taskErrors.assigne_a ? 'task-err-assigne_a' : undefined"
            >
              <option value="">Personne pour l’instant</option>
              <option v-for="p in staff" :key="p.id" :value="p.id">{{ p.nom }}</option>
            </select>
            <p v-if="taskErrors.assigne_a" id="task-err-assigne_a" class="error">{{ taskErrors.assigne_a }}</p>
            <button type="submit">{{ busy === 'task' ? 'Ajout…' : 'Ajouter la tâche' }}</button>
          </fieldset>
        </form>
      </template>
      <p v-else>Événement annulé : il n’est plus modifiable.</p>
      <p v-if="info.avec_devis" class="meta">
        Cet événement a un devis : il ne peut pas être supprimé (historique commercial). Annulez-le si nécessaire.
      </p>
      <template v-else>
        <button v-if="!confirmDelete" id="admin-delete-button" type="button" class="secondary" @click="askDelete">
          Supprimer l’événement…
        </button>
        <div
          v-else
          id="admin-delete-dialog"
          class="dialog"
          role="group"
          tabindex="-1"
          aria-labelledby="admin-delete-title"
        >
          <h3 id="admin-delete-title">Supprimer cet événement ?</h3>
          <p>L’événement, ses notes, ses tâches et ses avis sont supprimés définitivement.</p>
          <p class="actions">
            <button type="button" class="danger-button" :disabled="!!busy" @click="remove">
              {{ busy === 'delete' ? 'Suppression…' : 'Supprimer définitivement' }}</button
            ><button type="button" class="secondary" :disabled="!!busy" @click="cancelDelete">Annuler</button>
          </p>
        </div>
      </template>
    </template>
  </section>
</template>
<style scoped>
.admin {
  border: 2px solid #142d43;
  border-radius: 8px;
  padding: 16px;
  margin: 24px 0;
}
.admin h2 {
  margin-top: 0;
}
h3 {
  font-size: 1.1rem;
  margin: 16px 0 0;
}
.row {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
}
.row > div {
  flex: 1 1 200px;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
}
.meta {
  color: #4a5b68;
  font-size: 0.95rem;
}
.task {
  border-top: 1px solid #cbd5e1;
  margin-top: 16px;
}
.task button {
  margin-top: 16px;
}
.button-link {
  display: inline-flex;
  align-items: center;
  min-height: 48px;
  padding: 12px 20px;
  border-radius: 4px;
  background: #087f8c;
  color: #fff;
  text-decoration: none;
}
.button-link:hover {
  background: #06616b;
}
.dialog {
  border: 2px solid #b42318;
  border-radius: 8px;
  padding: 16px;
  margin-top: 12px;
}
.danger-button {
  background: #b42318;
}
.danger-button:hover {
  background: #8f1c13;
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
