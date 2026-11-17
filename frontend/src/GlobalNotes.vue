<script setup>
// Notes globales de l'équipe (communications sans rattachement à un événement).
// Toute l'équipe les lit ; l'administratrice les rédige, les modifie et les supprime.
import { nextTick, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, request, refresh } from './auth'
import { stamp } from './teamFormat'
import { noteError } from './noteRules'
import Icon from './Icon.vue'

const props = defineProps({ admin: { type: Boolean, default: false } })

const notes = ref([]),
  loading = ref(true),
  error = ref(''),
  notice = ref(''),
  actionError = ref('')
// Nouvelle note, note en cours de modification, note dont la suppression attend confirmation.
const draft = ref(''),
  draftError = ref(''),
  editing = ref(''),
  editText = ref(''),
  editError = ref(''),
  confirmDelete = ref(''),
  busy = ref('')
const router = useRouter()

onMounted(load)

/** Charge les notes ; une session expirée renvoie vers la connexion. */
async function load() {
  try {
    notes.value = (await request('/api/team/notes')).notes
    error.value = ''
  } catch (e) {
    if (e.status === 401) {
      await refresh()
      await router.replace('/connexion?retour=/espace')
    } else
      error.value =
        e.message === 'password_change_required'
          ? 'Un changement de mot de passe est requis avant de poursuivre.'
          : 'Les notes de l’équipe sont indisponibles pour le moment.'
  } finally {
    loading.value = false
  }
}

// Écriture JSON vers l'API avec le jeton CSRF ; la liste renvoyée remplace l'affichage.
async function post(path, body) {
  notes.value = (
    await request(path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify(body),
    })
  ).notes
}

async function focus(id) {
  await nextTick()
  document.getElementById(id)?.focus()
}

/** Message d'erreur commun ; fallback pour une réponse perdue. */
function fail(e, fallback) {
  return e.status === 401
    ? 'Votre session a expiré. Reconnectez-vous.'
    : e.status === 403
      ? 'Seule l’administratrice peut modifier les notes globales.'
      : e.status === 404
        ? 'Cette note n’existe plus : la liste a été rechargée.'
        : fallback
}

/** Publie une nouvelle note globale. */
async function addNote() {
  notice.value = actionError.value = ''
  draftError.value = noteError(draft.value)
  if (draftError.value) return focus('global-note-new')
  busy.value = 'new'
  try {
    await post('/api/team/global-note-save', { contenu: draft.value })
    draft.value = ''
    notice.value = 'Note publiée pour toute l’équipe.'
    busy.value = ''
    await focus('global-notice')
  } catch (e) {
    busy.value = ''
    if (e.status === 422) {
      draftError.value = e.fields?.contenu || 'Note invalide.'
      await focus('global-note-new')
    } else {
      actionError.value = fail(e, 'La note n’a pas pu être publiée. Votre texte est conservé : réessayez.')
      await focus('global-error')
    }
  }
}

async function startEdit(n) {
  editing.value = n.id
  editText.value = n.contenu
  editError.value = confirmDelete.value = ''
  await focus('global-note-edit-' + n.id)
}

async function cancelEdit(n) {
  editing.value = ''
  await focus('global-actions-' + n.id)
}

/** Enregistre la note modifiée. */
async function saveEdit(n) {
  notice.value = actionError.value = ''
  editError.value = noteError(editText.value)
  if (editError.value) return focus('global-note-edit-' + n.id)
  busy.value = n.id
  try {
    await post('/api/team/global-note-save', { id: n.id, contenu: editText.value })
    editing.value = ''
    notice.value = 'Note modifiée.'
    busy.value = ''
    await focus('global-notice')
  } catch (e) {
    busy.value = ''
    if (e.status === 422) {
      editError.value = e.fields?.contenu || 'Note invalide.'
      await focus('global-note-edit-' + n.id)
    } else {
      actionError.value = fail(e, 'La modification n’a pas pu être enregistrée. Réessayez.')
      editing.value = ''
      await load()
      await focus('global-error')
    }
  }
}

// Suppression en deux temps : demande, puis confirmation.
async function askDelete(n) {
  confirmDelete.value = n.id
  editing.value = ''
  await focus('global-delete-' + n.id)
}

async function cancelDelete(n) {
  confirmDelete.value = ''
  await focus('global-actions-' + n.id)
}

async function deleteNote(n) {
  busy.value = n.id
  notice.value = actionError.value = ''
  try {
    await post('/api/team/global-note-delete', { id: n.id })
    notice.value = 'Note supprimée.'
  } catch (e) {
    actionError.value = fail(e, 'La suppression n’a pas pu être confirmée. Réessayez.')
    await load()
  }
  confirmDelete.value = busy.value = ''
  await focus(actionError.value ? 'global-error' : 'global-notice')
}
</script>
<template>
  <div class="global-notes">
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <p v-if="notice" id="global-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
    <p v-if="actionError" id="global-error" class="error" role="alert" tabindex="-1">{{ actionError }}</p>

    <!-- Rédaction réservée à l'administratrice -->
    <form v-if="props.admin && !loading && !error" class="note-form" novalidate @submit.prevent="addNote">
      <label for="global-note-new">Nouvelle note pour toute l’équipe</label>
      <textarea
        id="global-note-new"
        v-model="draft"
        rows="3"
        maxlength="5000"
        :disabled="busy === 'new'"
        :aria-invalid="!!draftError"
        :aria-describedby="draftError ? 'global-note-new-error' : undefined"
      ></textarea>
      <p v-if="draftError" id="global-note-new-error" class="error">{{ draftError }}</p>
      <button type="submit" :disabled="!!busy">{{ busy === 'new' ? 'Publication…' : 'Publier la note' }}</button>
    </form>

    <ul v-if="!loading && !error" class="notes-box">
      <li v-for="n in notes" :key="n.id">
        <Icon name="info" class="note-icon" />
        <div class="note-body">
          <template v-if="editing === n.id">
            <label :for="'global-note-edit-' + n.id">Modifier la note</label>
            <textarea
              :id="'global-note-edit-' + n.id"
              v-model="editText"
              rows="4"
              maxlength="5000"
              :aria-invalid="!!editError"
              :aria-describedby="editError ? 'global-note-edit-error' : undefined"
            ></textarea>
            <p v-if="editError" id="global-note-edit-error" class="error">{{ editError }}</p>
            <p class="actions">
              <button type="button" :disabled="!!busy" @click="saveEdit(n)">
                {{ busy === n.id ? 'Enregistrement…' : 'Enregistrer' }}</button
              ><button type="button" class="secondary" :disabled="!!busy" @click="cancelEdit(n)">Annuler</button>
            </p>
          </template>
          <template v-else>
            <span class="text">{{ n.contenu }}</span>
            <span class="meta"
              >{{ n.auteur }} — {{ stamp(n.cree_le) }}<template v-if="n.modifiee"> (modifiée)</template></span
            >
            <div
              v-if="confirmDelete === n.id"
              :id="'global-delete-' + n.id"
              class="dialog"
              role="group"
              tabindex="-1"
              aria-label="Confirmer la suppression de la note globale"
            >
              <p>Supprimer définitivement cette note ?</p>
              <p class="actions">
                <button type="button" class="danger-button" :disabled="!!busy" @click="deleteNote(n)">
                  {{ busy === n.id ? 'Suppression…' : 'Supprimer la note' }}</button
                ><button type="button" class="secondary" :disabled="!!busy" @click="cancelDelete(n)">Annuler</button>
              </p>
            </div>
            <p v-else-if="n.modifiable" :id="'global-actions-' + n.id" class="actions" tabindex="-1">
              <button type="button" class="secondary" :disabled="!!busy" @click="startEdit(n)">Modifier</button>
              <button type="button" class="secondary" :disabled="!!busy" @click="askDelete(n)">Supprimer…</button>
            </p>
          </template>
        </div>
      </li>
      <li v-if="!notes.length">
        <Icon name="info" class="note-icon" /><span class="text">Aucune note pour le moment.</span>
      </li>
    </ul>
  </div>
</template>
<style scoped>
/* Notes sur fond sombre ; formulaire de rédaction sur fond clair au-dessus. */
.note-form {
  margin-bottom: 16px;
  padding: 18px 20px;
  background: var(--carte);
  border: 2px solid var(--encre);
  box-shadow: 4px 4px 0 var(--encre);
}
.note-form label {
  margin-top: 0;
}
.note-form button {
  margin-top: 12px;
}
.notes-box {
  margin: 0;
  padding: 8px 24px;
  list-style: none;
  color: white;
  background: var(--nuit);
  border-radius: 8px;
}
.notes-box li {
  display: flex;
  align-items: flex-start;
  gap: 20px;
  margin: 0;
  padding: 14px 0;
  border-top: 1px solid #3b4558;
}
.notes-box li:first-child {
  border-top: 0;
}
.note-icon {
  flex: none;
  width: 32px;
  height: 32px;
}
.note-body,
.notes-box li > .text {
  flex: 1;
  min-width: 0;
  padding-left: 20px;
  border-left: 1px solid #8a93a5;
}
.text {
  display: block;
  white-space: pre-line;
  overflow-wrap: anywhere;
}
.meta {
  display: block;
  margin-top: 4px;
  color: #c9cfdb;
  font-size: 0.9rem;
}
.notes-box label {
  color: white;
}
.notes-box textarea {
  width: 100%;
}
.actions {
  gap: 10px;
  margin: 10px 0 0;
}
.notes-box .secondary {
  color: var(--encre);
  background: var(--carte);
  border: 2px solid var(--carte);
}
.notes-box .error {
  color: #ffb4ab;
}
.dialog {
  margin-top: 10px;
  padding: 12px 16px;
  color: var(--encre);
}
.dialog .secondary {
  border-color: var(--encre);
}
/* Focus jaune sur le fond sombre, comme dans le pied de page. */
.notes-box :focus-visible {
  outline-color: var(--jaune);
}
</style>
