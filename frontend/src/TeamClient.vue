<script setup>
// Fiche client (équipe) : coordonnées et événements du client. L'administratrice peut la modifier ou la supprimer.
import { nextTick, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, request } from './auth'
import { eventStatuses, localDate } from './teamFormat'
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
  <main class="panel wide">
    <p><RouterLink to="/equipe/clients">← Tous les clients</RouterLink></p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <template v-if="data">
      <h1>{{ data.client.entreprise }}</h1>
      <p v-if="notice" id="client-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
      <p v-if="actionError" id="client-action-error" class="error" role="alert" tabindex="-1">{{ actionError }}</p>
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
        <dl class="facts">
          <div>
            <dt>Contact</dt>
            <dd>{{ data.client.contact }}</dd>
          </div>
          <div>
            <dt>Téléphone</dt>
            <dd>
              <a :href="'tel:' + data.client.telephone">{{ data.client.telephone }}</a>
            </dd>
          </div>
          <div>
            <dt>Adresse électronique</dt>
            <dd>
              <a :href="'mailto:' + data.client.email">{{ data.client.email }}</a>
            </dd>
          </div>
          <div v-if="data.client.adresse.length">
            <dt>Adresse</dt>
            <dd>
              <span v-for="(l, i) in data.client.adresse" :key="i">{{ l }}<br /></span>
            </dd>
          </div>
          <div>
            <dt>Espace client</dt>
            <dd>{{ data.client.compte ? 'Compte ouvert' : 'Pas de compte' }}</dd>
          </div>
        </dl>
        <div v-if="admin()" class="actions" role="group" aria-label="Gestion de la fiche client">
          <button id="client-edit-button" type="button" class="secondary" @click="startEdit">Modifier la fiche</button>
          <RouterLink
            class="button-link"
            :to="{ path: '/equipe/evenements/nouveau', query: { client: data.client.id } }"
            >Nouvel événement pour ce client</RouterLink
          >
          <button v-if="!confirmDelete" id="client-delete-button" type="button" class="secondary" @click="askDelete">
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
      <h2>Historique des événements</h2>
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
            <td>{{ eventStatuses[e.statut] || e.statut }}</td>
          </tr>
        </tbody>
      </table>
    </template>
  </main>
</template>
<style scoped>
.facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 12px;
  margin: 16px 0;
}
.facts div {
  background: #eef3f6;
  border-radius: 6px;
  padding: 12px;
}
dt {
  font-size: 0.9rem;
  color: #2f4250;
}
dd {
  margin: 4px 0 0;
  font-weight: 600;
  overflow-wrap: anywhere;
}
table {
  width: 100%;
  border-collapse: collapse;
}
th,
td {
  text-align: left;
  padding: 8px 6px;
  border-bottom: 1px solid #e2e8f0;
  overflow-wrap: anywhere;
}
@media (max-width: 600px) {
  thead {
    display: none;
  }
  tr {
    display: block;
    border-bottom: 1px solid #cbd5e1;
    padding: 8px 0;
  }
  td {
    display: block;
    border: 0;
    padding: 2px 0;
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
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: center;
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
.box {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 16px;
  margin: 16px 0;
}
.dialog {
  border: 2px solid #b42318;
  border-radius: 8px;
  padding: 16px;
  margin-top: 16px;
}
.dialog h2 {
  margin-top: 0;
  font-size: 1.15rem;
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
</style>
