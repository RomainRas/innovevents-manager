<script setup>
// Fiche événement (équipe) : informations, client, tâches et notes partagées.
// Chaque membre gère ses propres notes ; l'administratrice a en plus le panneau de gestion (AdminEventPanel).
import { nextTick, onMounted, ref } from 'vue'
import Icon from './Icon.vue'
import { useRoute } from 'vue-router'
import { auth, request } from './auth'
import { eventStatuses, localDate, stamp, taskStatuses } from './teamFormat'
import AdminEventPanel from './AdminEventPanel.vue'

const route = useRoute(),
  data = ref(null),
  error = ref(''),
  loading = ref(true)

// Nouvelle note, note en cours de modification, note dont la suppression attend confirmation.
const draft = ref(''),
  draftError = ref(''),
  editing = ref(''),
  editText = ref(''),
  editError = ref(''),
  confirmDelete = ref(''),
  busy = ref(''),
  notice = ref(''),
  actionError = ref('')

// Libellé du bouton qui fait avancer une tâche vers son statut suivant.
const nextLabels = { en_cours: 'Commencer la tâche', termine: 'Marquer comme terminée' }

onMounted(async () => {
  try {
    data.value = await request('/api/team/event?id=' + encodeURIComponent(route.params.id))
  } catch (e) {
    error.value =
      e.status === 404 || e.status === 422
        ? 'Cet événement n’existe pas.'
        : 'La fiche événement est indisponible pour le moment.'
  } finally {
    loading.value = false
  }
  if (route.query.message === 'cree') notice.value = 'Événement créé (brouillon).'
  else if (route.query.message === 'modifie') notice.value = 'Événement modifié.'
})

const isAdmin = () => auth.user?.role === 'administrateur'

/** Supprime une tâche (administratrice). */
async function deleteTask(t) {
  busy.value = 'task-' + t.id
  notice.value = ''
  actionError.value = ''
  let done = false
  try {
    await post('/api/admin/task-delete', { id: t.id })
    done = true
  } catch (e) {
    actionError.value = fail(e, 'La suppression de la tâche n’a pas pu être confirmée. Réessayez.')
  }
  // Liste rechargée avant d'annoncer le résultat : le message correspond à ce qui est affiché.
  await reload()
  if (done) notice.value = `Tâche « ${t.titre} » supprimée.`
  busy.value = ''
  await focus(actionError.value ? 'team-error' : 'team-notice')
}

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

/** Message d'erreur commun aux actions de la fiche ; fallback pour une réponse perdue. */
function fail(e, fallback) {
  return e.status === 401
    ? 'Votre session a expiré. Reconnectez-vous.'
    : e.status === 403
      ? 'Vous ne pouvez pas modifier cet élément.'
      : e.status === 404
        ? 'Cet élément n’existe plus : la fiche a été rechargée.'
        : e.status === 409
          ? 'Cet élément a été modifié entre-temps : la fiche a été rechargée.'
          : fallback
}

/** Recharge la fiche après une erreur (élément supprimé ou modifié par quelqu'un d'autre). */
async function reload() {
  try {
    data.value = await request('/api/team/event?id=' + encodeURIComponent(route.params.id))
  } catch {
    /* La fiche affichée reste visible. */
  }
}

/** Ajoute une note ; la fiche renvoyée par l'API remplace l'affichage. */
async function addNote() {
  draftError.value = ''
  notice.value = ''
  actionError.value = ''
  if (!draft.value.trim()) {
    draftError.value = 'Rédigez la note avant de l’ajouter.'
    return focus('note-new')
  }
  busy.value = 'new'
  try {
    data.value = await post('/api/team/note-save', { evenement_id: data.value.evenement.id, contenu: draft.value })
    draft.value = ''
    notice.value = 'Note ajoutée.'
    busy.value = ''
    await focus('team-notice')
  } catch (e) {
    busy.value = ''
    if (e.status === 422) {
      draftError.value = e.fields?.contenu || 'Note invalide.'
      await focus('note-new')
    } else {
      actionError.value = fail(e, 'La note n’a pas pu être enregistrée. Votre texte est conservé : réessayez.')
      await focus('team-error')
    }
  } finally {
    busy.value = ''
  }
}

// Modification d'une note sur place ; annuler rend le focus aux actions de la note.
async function startEdit(n) {
  editing.value = n.id
  editText.value = n.contenu
  editError.value = ''
  confirmDelete.value = ''
  await focus('note-edit-' + n.id)
}

async function cancelEdit(n) {
  editing.value = ''
  await focus('note-actions-' + n.id)
}

/** Enregistre la note modifiée (seul son auteur peut le faire, contrôlé par l'API). */
async function saveEdit(n) {
  editError.value = ''
  notice.value = ''
  actionError.value = ''
  if (!editText.value.trim()) {
    editError.value = 'La note ne peut pas être vide.'
    return focus('note-edit-' + n.id)
  }
  busy.value = n.id
  try {
    data.value = await post('/api/team/note-save', { id: n.id, contenu: editText.value })
    editing.value = ''
    notice.value = 'Note modifiée.'
    busy.value = ''
    await focus('team-notice')
  } catch (e) {
    busy.value = ''
    if (e.status === 422) {
      editError.value = e.fields?.contenu || 'Note invalide.'
      await focus('note-edit-' + n.id)
    } else {
      actionError.value = fail(e, 'La modification n’a pas pu être enregistrée. Réessayez.')
      editing.value = ''
      await reload()
      await focus('team-error')
    }
  } finally {
    busy.value = ''
  }
}

// Suppression d'une note en deux temps : demande, puis confirmation.
async function askDelete(n) {
  confirmDelete.value = n.id
  editing.value = ''
  await focus('note-delete-' + n.id)
}

async function cancelDelete(n) {
  confirmDelete.value = ''
  await focus('note-actions-' + n.id)
}

async function deleteNote(n) {
  busy.value = n.id
  notice.value = ''
  actionError.value = ''
  try {
    data.value = await post('/api/team/note-delete', { id: n.id })
    notice.value = 'Note supprimée.'
  } catch (e) {
    actionError.value = fail(e, 'La suppression n’a pas pu être confirmée. Réessayez.')
    await reload()
  }
  confirmDelete.value = ''
  busy.value = ''
  await focus(actionError.value ? 'team-error' : 'team-notice')
}

/** Fait passer une tâche à son statut suivant (à faire → en cours → terminée). */
async function advance(t) {
  busy.value = 'task-' + t.id
  notice.value = ''
  actionError.value = ''
  try {
    data.value = await post('/api/team/task-status', { id: t.id, statut: t.suivant })
    notice.value = `Tâche « ${t.titre} » : ${taskStatuses[t.suivant].toLowerCase()}.`
  } catch (e) {
    actionError.value = fail(e, 'Le changement de statut n’a pas pu être confirmé. Réessayez.')
    await reload()
  }
  busy.value = ''
  await focus(actionError.value ? 'team-error' : 'team-notice')
}
</script>
<template>
  <main class="work-page">
    <p><RouterLink class="back-link" to="/equipe/evenements">← Tous les événements</RouterLink></p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <template v-if="data">
      <h1 class="event-title">{{ data.evenement.nom }}</h1>
      <!-- Informations de l'événement et client associé -->
      <dl class="facts">
        <div>
          <span class="tile yellow"><Icon name="flag" /></span>
          <dt>Statut</dt>
          <dd>{{ eventStatuses[data.evenement.statut] || data.evenement.statut }}</dd>
        </div>
        <div>
          <span class="tile red"><Icon name="calendar" /></span>
          <dt>Début</dt>
          <dd>{{ localDate(data.evenement.debut) }}</dd>
        </div>
        <div>
          <span class="tile red"><Icon name="calendar" /></span>
          <dt>Fin</dt>
          <dd>{{ localDate(data.evenement.fin) }}</dd>
        </div>
        <div>
          <span class="tile blue"><Icon name="pin" /></span>
          <dt>Lieu</dt>
          <dd>{{ data.evenement.lieu || 'À définir' }}</dd>
        </div>
        <div>
          <span class="tile yellow"><Icon name="tag" /></span>
          <dt>Type et thème</dt>
          <dd>
            {{ data.evenement.type_evenement || '—'
            }}<template v-if="data.evenement.theme"> — {{ data.evenement.theme }}</template>
          </dd>
        </div>
        <div>
          <span class="tile blue"><Icon name="user" /></span>
          <dt>Participants</dt>
          <dd>{{ data.evenement.participants ?? '—' }}</dd>
        </div>
        <div>
          <span class="tile red"><Icon name="building" /></span>
          <dt>Client</dt>
          <dd>
            <RouterLink :to="'/equipe/clients/' + data.evenement.client_id">{{ data.evenement.client }}</RouterLink
            ><br />{{ data.evenement.contact }}<br /><a :href="'tel:' + data.evenement.telephone">{{
              data.evenement.telephone
            }}</a>
          </dd>
        </div>
      </dl>
      <p v-if="actionError" id="team-error" class="error" role="alert" tabindex="-1">{{ actionError }}</p>
      <p v-if="notice" id="team-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>

      <!-- Gestion de l'événement, administratrice seulement -->
      <AdminEventPanel v-if="isAdmin()" :event-id="data.evenement.id" @changed="reload" />

      <!-- Tâches : bouton vers l'étape suivante, affiché seulement si l'API l'autorise -->
      <section aria-labelledby="tasks-title">
        <h2 id="tasks-title" class="slash-title blue">Tâches</h2>
        <p v-if="!data.taches.length">Aucune tâche pour cet événement.</p>
        <ul class="list">
          <li v-for="t in data.taches" :key="t.id" :class="{ mine: t.a_moi }">
            <h3 :id="'task-' + t.id">
              {{ t.titre }} <span class="badge" :class="t.statut">{{ taskStatuses[t.statut] }}</span>
            </h3>
            <p v-if="t.description" class="text">{{ t.description }}</p>
            <p class="meta">
              {{ t.assigne ? 'Assignée à ' + t.assigne : 'Non assignée' }}<template v-if="t.a_moi"> (vous)</template>
            </p>
            <p class="actions">
              <button
                v-if="t.suivant"
                type="button"
                :disabled="!!busy"
                :aria-describedby="'task-' + t.id"
                @click="advance(t)"
              >
                {{ busy === 'task-' + t.id ? 'Enregistrement…' : nextLabels[t.suivant] }}
              </button>
              <button
                v-if="isAdmin()"
                type="button"
                class="secondary"
                :disabled="!!busy"
                :aria-describedby="'task-' + t.id"
                @click="deleteTask(t)"
              >
                Supprimer la tâche
              </button>
            </p>
          </li>
        </ul>
      </section>

      <!-- Notes partagées : ajout, puis modification ou suppression de ses propres notes -->
      <section aria-labelledby="notes-title">
        <h2 id="notes-title" class="slash-title">Notes de l’équipe</h2>
        <form class="note-form" novalidate @submit.prevent="addNote">
          <label for="note-new">Ajouter une note</label>
          <textarea
            id="note-new"
            v-model="draft"
            rows="3"
            maxlength="5000"
            :disabled="busy === 'new'"
            :aria-invalid="!!draftError"
            :aria-describedby="draftError ? 'note-new-error' : undefined"
          ></textarea>
          <p v-if="draftError" id="note-new-error" class="error">{{ draftError }}</p>
          <button type="submit" :disabled="!!busy">{{ busy === 'new' ? 'Ajout…' : 'Ajouter la note' }}</button>
        </form>
        <p v-if="!data.notes.length">Aucune note pour le moment.</p>
        <ul class="list">
          <li v-for="n in data.notes" :key="n.id">
            <p class="meta">
              {{ n.auteur }} — {{ stamp(n.cree_le) }}<template v-if="n.modifiee"> (modifiée)</template>
            </p>
            <template v-if="editing === n.id">
              <label :for="'note-edit-' + n.id">Modifier la note</label>
              <textarea
                :id="'note-edit-' + n.id"
                v-model="editText"
                rows="4"
                maxlength="5000"
                :aria-invalid="!!editError"
                :aria-describedby="editError ? 'note-edit-error' : undefined"
              ></textarea>
              <p v-if="editError" id="note-edit-error" class="error">{{ editError }}</p>
              <p class="actions">
                <button type="button" :disabled="!!busy" @click="saveEdit(n)">
                  {{ busy === n.id ? 'Enregistrement…' : 'Enregistrer' }}</button
                ><button type="button" class="secondary" :disabled="!!busy" @click="cancelEdit(n)">Annuler</button>
              </p>
            </template>
            <template v-else>
              <p class="text">{{ n.contenu }}</p>
              <div
                v-if="confirmDelete === n.id"
                :id="'note-delete-' + n.id"
                class="dialog"
                role="group"
                tabindex="-1"
                :aria-label="'Confirmer la suppression de la note de ' + n.auteur"
              >
                <p>Supprimer définitivement cette note ?</p>
                <p class="actions">
                  <button type="button" class="danger-button" :disabled="!!busy" @click="deleteNote(n)">
                    {{ busy === n.id ? 'Suppression…' : 'Supprimer la note' }}</button
                  ><button type="button" class="secondary" :disabled="!!busy" @click="cancelDelete(n)">Annuler</button>
                </p>
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
/* Fiche événement (équipe) : grand titre, informations à pastilles colorées, tâches et notes en cartes. */
.event-title {
  margin: 8px 0 24px;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: clamp(40px, 5.5vw, 72px);
  line-height: 1;
  text-transform: uppercase;
  color: var(--encre);
  overflow-wrap: anywhere;
}
.event-title::after {
  content: '';
  display: block;
  width: 200px;
  max-width: 60%;
  height: 8px;
  margin-top: 12px;
  background: var(--jaune);
  transform: rotate(-1.5deg);
}
.facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 0;
  margin: 0 0 24px;
  background: var(--creme);
  border: 2px solid var(--encre);
}
.facts div {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0 14px;
  align-items: center;
  padding: 14px 18px;
  border-bottom: 1px solid var(--filet);
}
.tile {
  grid-row: span 2;
  display: grid;
  place-items: center;
  width: 46px;
  height: 42px;
  color: var(--encre);
  clip-path: polygon(4% 8%, 96% 0, 100% 92%, 0 100%);
}
.tile.red {
  background: #d9342b;
}
.tile.blue {
  background: var(--bleu);
}
.tile.yellow {
  background: var(--jaune);
}
.tile :deep(.icon) {
  stroke-width: 2.2;
}
dt {
  font-weight: 800;
}
dd {
  margin: 0;
  overflow-wrap: anywhere;
}
dd a {
  color: var(--bleu);
}
.list {
  list-style: none;
  margin: 0;
  padding: 0;
}
.list li {
  margin: 0 0 12px;
  padding: 16px 20px;
  background: var(--creme);
  border: 1px solid var(--filet);
  border-left: 5px solid var(--filet);
  border-radius: 8px;
}
.list li.mine {
  border-left-color: var(--bleu);
}
h3 {
  margin: 0 0 4px;
  font-size: 1.15rem;
  overflow-wrap: anywhere;
}
.badge {
  display: inline-block;
  margin-left: 6px;
  padding: 2px 10px;
  border-radius: 999px;
  background: var(--filet-clair);
  font-size: 0.85rem;
  font-weight: 600;
}
.badge.en_cours {
  background: #fff1a8;
  color: var(--encre);
}
.badge.termine {
  background: #d9f0e1;
  color: var(--succes);
}
.text {
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.meta {
  margin: 4px 0;
}
.actions {
  gap: 10px;
  margin: 10px 0 0;
}
.actions button {
  border-radius: 6px;
}
.actions .secondary {
  border: 2px solid var(--encre);
}
.note-form {
  margin-bottom: 20px;
  padding: 18px 20px;
  background: var(--creme);
  border: 1px solid var(--filet);
  border-radius: 8px;
}
.note-form label {
  margin-top: 0;
}
form button {
  margin-top: 12px;
  border-radius: 6px;
}
.dialog {
  margin-top: 10px;
  padding: 12px 16px;
}
</style>
