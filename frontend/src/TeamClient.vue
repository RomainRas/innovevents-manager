<script setup>
// Fiche client (équipe) : coordonnées et événements du client. L'administratrice peut la modifier ou la supprimer.
import { nextTick, onMounted, ref } from 'vue'
import Icon from './Icon.vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, request } from './auth'
import { eventStatuses, localDate } from './teamFormat'

// Couleur de la pastille de statut des événements.
const tones = { brouillon: 'grey', accepte: 'green', en_cours: 'blue', termine: 'yellow', annule: 'red' }
import ClientForm from './ClientForm.vue'

const route = useRoute(),
  router = useRouter(),
  data = ref(null),
  error = ref(''),
  loading = ref(true)
const admin = () => auth.user?.role === 'administrateur'
const editing = ref(false),
  confirmDelete = ref(false),
  busy = ref(false),
  notice = ref(''),
  actionError = ref('')

// Raisons pour lesquelles une fiche ne peut pas être supprimée (historique à conserver).
const deleteErrors = {
  client_has_account: 'Ce client a un compte : supprimez d’abord le compte depuis « Comptes ».',
  client_has_events: 'Ce client a des événements : la fiche est conservée pour l’historique.',
  client_has_requests: 'Ce client est lié à des demandes de devis : la fiche est conservée pour l’historique.',
}

async function load() {
  try {
    data.value = await request('/api/team/client?id=' + encodeURIComponent(route.params.id))
  } catch (e) {
    error.value =
      e.status === 404 || e.status === 422
        ? 'Ce client n’existe pas.'
        : 'La fiche client est indisponible pour le moment.'
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  await load()
  if (route.query.message === 'cree') notice.value = 'Fiche client créée.'
})

// Place le focus sur un élément après la mise à jour de l'affichage.
async function focus(id) {
  await nextTick()
  document.getElementById(id)?.focus()
}

// Modification de la fiche dans un formulaire affiché sur la page.
async function startEdit() {
  editing.value = true
  notice.value = ''
  actionError.value = ''
  await focus('client-edit-entreprise')
}

async function saved() {
  editing.value = false
  await load()
  notice.value = 'Fiche client enregistrée.'
  await focus('client-notice')
}

async function cancelEdit() {
  editing.value = false
  await focus('client-edit-button')
}

// Suppression en deux temps : demande, puis confirmation (le focus revient au bouton si on annule).
async function askDelete() {
  confirmDelete.value = true
  notice.value = ''
  actionError.value = ''
  await focus('client-delete-dialog')
}

async function cancelDelete() {
  confirmDelete.value = false
  await focus('client-delete-button')
}

/** Supprime la fiche puis revient à la liste ; un refus de l'API est expliqué (compte, événements, demandes). */
async function remove() {
  busy.value = true
  try {
    await request('/api/admin/client-delete', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify({ id: route.params.id, confirmation: true }),
    })
    await router.replace({ path: '/equipe/clients', query: { message: 'supprime' } })
  } catch (e) {
    actionError.value =
      deleteErrors[e.message] ||
      (e.status === 404 ? 'Cette fiche a déjà été supprimée.' : 'La suppression n’a pas pu être confirmée. Réessayez.')
    confirmDelete.value = false
    busy.value = false
    await focus('client-action-error')
  }
}
</script>
<template>
  <main class="work-page">
    <p><RouterLink class="back-link" to="/equipe/clients">← Tous les clients</RouterLink></p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <template v-if="data">
      <div class="client-head">
        <span class="result-tile yellow" aria-hidden="true"><Icon name="building" /></span>
        <div>
          <h1>{{ data.client.entreprise }}</h1>
          <span class="status-pill">{{ data.client.compte ? 'Espace client ouvert' : 'Pas d’espace client' }}</span>
        </div>
      </div>
      <p v-if="notice" id="client-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
      <p v-if="actionError" id="client-action-error" class="error" role="alert" tabindex="-1">{{ actionError }}</p>
      <!-- Modification de la fiche (administratrice) -->
      <section v-if="admin() && editing" aria-labelledby="client-edit-title" class="box">
        <h2 id="client-edit-title">Modifier la fiche</h2>
        <ClientForm
          :initial="data.client.champs"
          :client-id="data.client.id"
          submit-label="Enregistrer la fiche"
          @saved="saved"
          @cancel="cancelEdit"
        />
      </section>
      <template v-else>
        <!-- Coordonnées du client -->
        <dl class="facts">
          <div>
            <span class="fact-icon"><Icon name="user" /></span>
            <dt>Contact</dt>
            <dd>{{ data.client.contact }}</dd>
          </div>
          <div>
            <span class="fact-icon"><Icon name="phone" /></span>
            <dt>Téléphone</dt>
            <dd>
              <a :href="'tel:' + data.client.telephone">{{ data.client.telephone }}</a>
            </dd>
          </div>
          <div>
            <span class="fact-icon"><Icon name="mail" /></span>
            <dt>Adresse électronique</dt>
            <dd>
              <a :href="'mailto:' + data.client.email">{{ data.client.email }}</a>
            </dd>
          </div>
          <div v-if="data.client.adresse.length">
            <span class="fact-icon"><Icon name="pin" /></span>
            <dt>Adresse</dt>
            <dd>
              <span v-for="(l, i) in data.client.adresse" :key="i">{{ l }}<br /></span>
            </dd>
          </div>
          <div>
            <span class="fact-icon"><Icon name="users" /></span>
            <dt>Espace client</dt>
            <dd>{{ data.client.compte ? 'Compte ouvert' : 'Pas de compte' }}</dd>
          </div>
        </dl>
        <!-- Actions de l'administratrice : modifier, supprimer (avec confirmation) -->
        <div v-if="admin()" class="actions" role="group" aria-label="Gestion de la fiche client">
          <button id="client-edit-button" type="button" class="secondary edit" @click="startEdit">
            <Icon name="pen" />Modifier la fiche
          </button>
          <RouterLink
            class="button-link"
            :to="{ path: '/equipe/evenements/nouveau', query: { client: data.client.id } }"
            >Nouvel événement pour ce client</RouterLink
          >
          <button
            v-if="!confirmDelete"
            id="client-delete-button"
            type="button"
            class="secondary delete"
            @click="askDelete"
          >
            <Icon name="trash" />
            Supprimer la fiche…
          </button>
        </div>
        <div
          v-if="confirmDelete"
          id="client-delete-dialog"
          class="dialog"
          role="group"
          tabindex="-1"
          aria-labelledby="client-delete-title"
        >
          <h2 id="client-delete-title">Supprimer la fiche « {{ data.client.entreprise }} » ?</h2>
          <p>
            La suppression est définitive. Elle n’est possible que pour un client sans compte, sans événement et sans
            demande de devis.
          </p>
          <p class="actions">
            <button type="button" class="danger-button" :disabled="busy" @click="remove">
              {{ busy ? 'Suppression…' : 'Supprimer définitivement' }}</button
            ><button type="button" class="secondary" :disabled="busy" @click="cancelDelete">Annuler</button>
          </p>
        </div>
      </template>
      <!-- Événements du client -->
      <h2 class="history-title"><Icon name="calendar" />Historique des événements</h2>
      <p v-if="!data.evenements.length">Aucun événement pour ce client.</p>
      <table v-else>
        <caption class="sr-only">
          Événements du client, du plus récent au plus ancien
        </caption>
        <thead>
          <tr>
            <th scope="col">Événement</th>
            <th scope="col">Date</th>
            <th scope="col">Lieu</th>
            <th scope="col">Statut</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="e in data.evenements" :key="e.id">
            <td>
              <RouterLink :to="'/equipe/evenements/' + e.id">{{ e.nom }}</RouterLink>
            </td>
            <td>{{ localDate(e.debut, false) }}</td>
            <td>{{ e.lieu || '—' }}</td>
            <td>
              <span class="status-pill" :class="tones[e.statut]">{{ eventStatuses[e.statut] || e.statut }}</span>
            </td>
          </tr>
        </tbody>
      </table>
    </template>
  </main>
</template>
<style scoped>
/* Fiche client : en-tête à pavé, coordonnées en cartes à pictogramme, actions, historique en tableau. */
.client-head {
  display: flex;
  align-items: center;
  gap: 20px;
  margin: 8px 0 24px;
}
.client-head .result-tile {
  width: 84px;
  height: 84px;
  border-radius: 12px;
}
.client-head .result-tile :deep(.icon) {
  width: 44px;
  height: 44px;
}
h1 {
  margin: 0 0 6px;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-weight: 400;
  font-size: clamp(36px, 5vw, 56px);
  line-height: 1.05;
  color: #111;
  overflow-wrap: anywhere;
}
.facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 14px;
  margin: 0 0 20px;
}
.facts div {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 2px 14px;
  align-items: start;
  padding: 16px 18px;
  background: #fbf8f1;
  border: 1px solid #d8cfbd;
  border-radius: 8px;
}
.fact-icon {
  grid-row: span 2;
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  background: #efe8d8;
}
.fact-icon :deep(.icon) {
  width: 22px;
  height: 22px;
}
dt {
  font-size: 0.92rem;
  color: #4a4a4a;
}
dd {
  margin: 0;
  font-weight: 700;
  overflow-wrap: anywhere;
}
dd a {
  color: #1d3fbf;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
  padding-bottom: 24px;
  border-bottom: 1px solid #d8cfbd;
}
.actions > * {
  flex: 1 1 220px;
  justify-content: center;
}
.actions button,
.button-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 48px;
  padding: 12px 20px;
  border-radius: 6px;
  font-weight: 700;
}
.edit {
  color: white !important;
  background: #172033 !important;
  border: 0 !important;
}
.button-link {
  color: #fff;
  background: #c8221a;
  text-decoration: none;
}
.button-link:hover {
  background: #a51b14;
}
.delete {
  color: #c8221a !important;
  border: 2px solid #c8221a !important;
  background: #fffdf8 !important;
}
.history-title {
  display: flex;
  align-items: center;
  gap: 14px;
  margin: 28px 0 14px;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-weight: 400;
  font-size: 2rem;
  color: #111;
}
.history-title :deep(.icon) {
  width: 34px;
  height: 34px;
  color: #d4a600;
}
table {
  width: 100%;
  border-collapse: collapse;
  background: #fbf8f1;
  border: 1px solid #d8cfbd;
  border-radius: 8px;
  overflow: hidden;
}
th,
td {
  text-align: left;
  padding: 12px 16px;
  border-bottom: 1px solid #e5dccb;
  overflow-wrap: anywhere;
}
th {
  background: #efe8d8;
}
td a {
  color: #111;
  font-weight: 700;
}
@media (max-width: 600px) {
  thead {
    display: none;
  }
  tr {
    display: block;
    border-bottom: 1px solid #d8cfbd;
    padding: 8px 0;
  }
  td {
    display: block;
    border: 0;
    padding: 2px 12px;
  }
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
.box {
  padding: 20px;
  margin: 16px 0;
  background: #fbf8f1;
  border: 1px solid #d8cfbd;
  border-radius: 8px;
}
.dialog {
  margin-top: 12px;
  padding: 16px;
  border: 2px solid #b42318;
  border-radius: 8px;
  background: #fbe4e1;
}
.danger-button {
  background: #b42318 !important;
}
.success {
  color: #0b6b3a;
  font-weight: 600;
}
</style>
