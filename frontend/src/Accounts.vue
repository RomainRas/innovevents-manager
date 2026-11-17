<script setup>
// Gestion des comptes (administratrice) : liste filtrable, création d'un compte employé ou client,
// suspension, réactivation et suppression avec confirmation.
import { nextTick, onMounted, reactive, ref } from 'vue'
import Icon from './Icon.vue'
import { auth, request } from './auth'

const accounts = ref([]),
  free = ref([]),
  loading = ref(true),
  error = ref(''),
  notice = ref(''),
  busy = ref('')

// Filtres de la liste et formulaire de création (un compte client se rattache à une fiche client sans compte).
const filter = reactive({ role: '', q: '' })
const form = reactive({ role: 'employe', prenom: '', nom: '', email: '', acces_mobile: false, client_id: '' }),
  errors = ref({}),
  formError = ref('')

// Compte dont la suppression attend confirmation.
const pendingDelete = ref(null)
const roles = { client: 'Client', employe: 'Employé' }
const day = v =>
  v ? new Date(v.replace(' ', 'T').slice(0, 19) + 'Z').toLocaleDateString('fr-FR', { timeZone: 'Europe/Paris' }) : ''

// Écriture JSON vers l'API avec le jeton CSRF.
const post = (path, body) =>
  request(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
    body: JSON.stringify(body),
  })

/** Charge les comptes et les fiches clients qui n'ont pas encore de compte. */
async function load() {
  loading.value = true
  error.value = ''
  try {
    const data = await request('/api/admin/accounts?' + new URLSearchParams({ role: filter.role, q: filter.q }))
    accounts.value = data.comptes
    free.value = data.fiches_sans_compte
  } catch {
    error.value = 'La liste des comptes est indisponible pour le moment.'
  } finally {
    loading.value = false
  }
}

onMounted(load)

// Place le focus sur un élément après la mise à jour de l'affichage (messages, champ en erreur).
async function focus(id) {
  await nextTick()
  document.getElementById(id)?.focus()
}

/** Crée le compte : les identifiants sont envoyés par courriel, avec un mot de passe à changer à la première connexion. */
async function create() {
  busy.value = 'create'
  errors.value = {}
  formError.value = ''
  notice.value = ''
  const body =
    form.role === 'client'
      ? { role: 'client', client_id: form.client_id }
      : { role: 'employe', prenom: form.prenom, nom: form.nom, email: form.email, acces_mobile: form.acces_mobile }
  try {
    const r = await post('/api/admin/account-create', body)
    Object.assign(form, { prenom: '', nom: '', email: '', acces_mobile: false, client_id: '' })
    // Liste rechargée avant d'annoncer le résultat : le message correspond à ce qui est affiché.
    busy.value = ''
    await load()
    notice.value = `Compte ${roles[r.role].toLowerCase()} créé pour ${r.email}. Les identifiants lui ont été envoyés par courriel ; un nouveau mot de passe lui sera demandé à la première connexion.`
    await focus('accounts-notice')
  } catch (e) {
    errors.value = e.fields || {}
    formError.value =
      e.status === 422
        ? form.role === 'client' && !form.client_id
          ? 'Choisissez la fiche client à rattacher.'
          : 'Vérifiez les champs signalés.'
        : e.status === 409
          ? e.message === 'email_taken'
            ? 'Un compte utilise déjà cette adresse électronique.'
            : 'Cette fiche client a déjà un compte.'
          : e.status === 404
            ? 'Cette fiche client n’existe plus.'
            : 'La création n’a pas pu être confirmée. Rechargez la liste avant de réessayer.'
    busy.value = ''
    const first = Object.keys(errors.value)[0]
    await focus(first ? 'create-' + first : form.role === 'client' ? 'create-client_id' : 'create-error')
  } finally {
    busy.value = ''
  }
}

/** Suspend (connexion bloquée immédiatement) ou réactive un compte. */
async function setStatus(a, statut) {
  busy.value = a.id
  error.value = ''
  notice.value = ''
  let done = ''
  try {
    await post('/api/admin/account-status', { id: a.id, statut })
    done =
      statut === 'suspendu'
        ? `Compte de ${a.prenom} ${a.nom} suspendu : la connexion est bloquée immédiatement.`
        : `Compte de ${a.prenom} ${a.nom} réactivé.`
  } catch (e) {
    error.value =
      e.status === 404
        ? 'Ce compte n’existe plus.'
        : 'La modification n’a pas pu être confirmée. Rechargez la liste avant de réessayer.'
  }
  busy.value = ''
  await load()
  notice.value = done
  await focus(error.value ? 'accounts-error' : 'accounts-notice')
}

// Suppression en deux temps : demande, puis confirmation dans un panneau (le focus revient au bouton si on annule).
async function askDelete(a) {
  pendingDelete.value = a
  notice.value = ''
  error.value = ''
  await focus('delete-dialog')
}

async function cancelDelete() {
  const id = pendingDelete.value.id
  pendingDelete.value = null
  await focus('delete-' + id)
}

/** Supprime le compte : informations personnelles effacées, confirmation envoyée par courriel. */
async function confirmDelete() {
  const a = pendingDelete.value
  busy.value = a.id
  let done = ''
  try {
    await post('/api/admin/account-delete', { id: a.id, confirmation: true })
    done = `Compte de ${a.prenom} ${a.nom} supprimé : informations personnelles effacées, confirmation envoyée par courriel.`
  } catch (e) {
    error.value =
      e.status === 404
        ? 'Ce compte a déjà été supprimé.'
        : 'La suppression n’a pas pu être confirmée. Rechargez la liste avant de réessayer.'
  }
  pendingDelete.value = null
  busy.value = ''
  await load()
  notice.value = done
  await focus(error.value ? 'accounts-error' : 'accounts-notice')
}
</script>
<template>
  <main class="work-page">
    <p><RouterLink class="back-link" to="/espace">← Retour au tableau de bord</RouterLink></p>
    <h1 class="accounts-title"><Icon name="users" />Comptes employés et clients</h1>
    <p v-if="error" id="accounts-error" class="error" role="alert" tabindex="-1">{{ error }}</p>
    <p v-if="notice" id="accounts-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>

    <!-- Création d'un compte : employé (identité) ou client (fiche client à rattacher) -->
    <section aria-labelledby="create-title" class="box">
      <h2 id="create-title" class="box-head">Créer un compte</h2>
      <p v-if="formError" id="create-error" class="error" role="alert" tabindex="-1">{{ formError }}</p>
      <form novalidate :aria-busy="busy === 'create'" @submit.prevent="create">
        <fieldset :disabled="busy === 'create'">
          <legend>Type de compte</legend>
          <p class="radios">
            <label><input v-model="form.role" type="radio" value="employe" /> Employé</label>
            <label><input v-model="form.role" type="radio" value="client" /> Client (fiche existante)</label>
          </p>
          <template v-if="form.role === 'employe'">
            <div class="grid">
              <div>
                <label for="create-prenom">Prénom *</label
                ><input
                  id="create-prenom"
                  v-model="form.prenom"
                  maxlength="100"
                  autocomplete="off"
                  :aria-invalid="!!errors.prenom"
                  :aria-describedby="errors.prenom ? 'create-err-prenom' : undefined"
                />
                <p v-if="errors.prenom" id="create-err-prenom" class="error">{{ errors.prenom }}</p>
              </div>
              <div>
                <label for="create-nom">Nom *</label
                ><input
                  id="create-nom"
                  v-model="form.nom"
                  maxlength="100"
                  autocomplete="off"
                  :aria-invalid="!!errors.nom"
                  :aria-describedby="errors.nom ? 'create-err-nom' : undefined"
                />
                <p v-if="errors.nom" id="create-err-nom" class="error">{{ errors.nom }}</p>
              </div>
            </div>
            <label for="create-email">Adresse électronique *</label>
            <input
              id="create-email"
              v-model="form.email"
              type="email"
              maxlength="254"
              autocomplete="off"
              :aria-invalid="!!errors.email"
              :aria-describedby="errors.email ? 'create-err-email' : undefined"
            />
            <p v-if="errors.email" id="create-err-email" class="error">{{ errors.email }}</p>
            <p class="check">
              <input id="create-mobile" v-model="form.acces_mobile" type="checkbox" /><label for="create-mobile"
                >Accès à l’application mobile</label
              >
            </p>
          </template>
          <template v-else>
            <label for="create-client_id">Fiche client *</label>
            <select
              id="create-client_id"
              v-model="form.client_id"
              :aria-invalid="!!errors.client_id"
              aria-describedby="create-client-help"
            >
              <option value="">— Choisir une fiche sans compte —</option>
              <option v-for="f in free" :key="f.id" :value="f.id">
                {{ f.entreprise }} — {{ f.contact }} ({{ f.email }})
              </option>
            </select>
            <p id="create-client-help" class="meta">
              {{
                errors.client_id ||
                (free.length
                  ? 'Le compte reprend le nom et l’adresse électronique de la fiche.'
                  : 'Toutes les fiches client ont déjà un compte.')
              }}
            </p>
          </template>
          <button type="submit" class="send">
            <svg aria-hidden="true" viewBox="0 0 24 24"><path d="m3 11 18-8-8 18-2-8z" /></svg
            >{{ busy === 'create' ? 'Création…' : 'Créer le compte et envoyer les identifiants' }}
          </button>
        </fieldset>
      </form>
    </section>

    <!-- Liste des comptes, filtrable par rôle et par recherche -->
    <section aria-labelledby="list-title" class="box">
      <h2 id="list-title" class="box-head">Comptes</h2>
      <form class="filters" role="search" @submit.prevent="load">
        <div>
          <label for="filter-q">Rechercher (nom, adresse, entreprise)</label
          ><input id="filter-q" v-model="filter.q" type="search" maxlength="100" />
        </div>
        <div>
          <label for="filter-role">Type</label
          ><select id="filter-role" v-model="filter.role" @change="load">
            <option value="">Tous</option>
            <option value="employe">Employés</option>
            <option value="client">Clients</option>
          </select>
        </div>
        <button type="submit" class="search-button"><Icon name="search" />Rechercher</button>
      </form>
      <p v-if="loading" role="status">Chargement…</p>
      <p v-else-if="!accounts.length">Aucun compte ne correspond.</p>
      <!-- Un compte par ligne : statut, accès mobile, suspension, réactivation, suppression -->
      <ul class="accounts">
        <li
          v-for="a in accounts"
          :key="a.id"
          :class="{ suspended: a.statut === 'suspendu', pending: a.suppression_demandee_le }"
        >
          <span class="avatar" :class="a.role" aria-hidden="true">{{
            ((a.prenom || ' ')[0] + (a.nom || ' ')[0]).toUpperCase()
          }}</span>
          <h3 :id="'account-' + a.id">
            {{ a.prenom }} {{ a.nom }} <span class="badge">{{ roles[a.role] }}</span
            ><span v-if="a.statut === 'suspendu'" class="badge warn">Suspendu</span>
          </h3>
          <p class="meta">
            {{ a.email }}<template v-if="a.entreprise"> — {{ a.entreprise }}</template> — créé le {{ day(a.cree_le)
            }}<template v-if="a.acces_mobile"> — accès mobile</template
            ><template v-if="a.premiere_connexion"> — première connexion en attente</template>
          </p>
          <p v-if="a.suppression_demandee_le" class="alert">
            <strong>Suppression demandée par le client le {{ day(a.suppression_demandee_le) }}.</strong>
          </p>
          <p v-if="pendingDelete?.id !== a.id" class="actions" role="group" :aria-labelledby="'account-' + a.id">
            <button
              v-if="a.statut === 'actif'"
              type="button"
              class="secondary"
              :disabled="!!busy"
              @click="setStatus(a, 'suspendu')"
            >
              Suspendre
            </button>
            <button v-else type="button" class="secondary" :disabled="!!busy" @click="setStatus(a, 'actif')">
              Réactiver
            </button>
            <button :id="'delete-' + a.id" type="button" class="danger-button" :disabled="!!busy" @click="askDelete(a)">
              Supprimer…
            </button>
          </p>
          <div v-else id="delete-dialog" class="dialog" role="group" tabindex="-1" aria-labelledby="delete-title">
            <h4 id="delete-title">Supprimer le compte de {{ a.prenom }} {{ a.nom }} ?</h4>
            <p>
              Les informations personnelles sont effacées définitivement<template v-if="a.role === 'client'">
                (compte, coordonnées de la fiche client, avis)</template
              >. Les devis restent conservés sans coordonnées. Cette action ne peut pas être annulée.
            </p>
            <p class="actions">
              <button type="button" class="danger-button" :disabled="!!busy" @click="confirmDelete">
                {{ busy === a.id ? 'Suppression…' : 'Supprimer définitivement' }}</button
              ><button type="button" class="secondary" :disabled="!!busy" @click="cancelDelete">Annuler</button>
            </p>
          </div>
        </li>
      </ul>
    </section>
  </main>
</template>
<style scoped>
/* Comptes : deux cartes à bandeau (création, liste) ; un compte par ligne avec initiales et actions. */
.accounts-title {
  display: flex;
  align-items: center;
  gap: 16px;
  margin: 8px 0 24px;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: clamp(34px, 4.5vw, 52px);
  line-height: 1.1;
  color: var(--encre);
}
.accounts-title :deep(.icon) {
  width: 44px;
  height: 44px;
}
.box {
  margin: 0 0 28px;
  padding: 0 24px 24px;
  background: var(--creme);
  border: 1px solid var(--nuit);
  border-radius: 10px;
  overflow: hidden;
}
.box-head {
  margin: 0 -24px 16px;
  padding: 14px 24px;
  background: var(--creme-fonce);
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: 1.8rem;
  color: var(--encre);
}
legend {
  font-weight: 700;
  padding: 0;
}
.radios {
  display: flex;
  flex-wrap: wrap;
  gap: 24px;
  margin: 8px 0;
}
.radios label {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0;
  font-weight: 400;
}
.radios input,
.check input {
  width: 24px;
  height: 24px;
  min-height: 0;
  margin: 0;
  flex: none;
  accent-color: var(--bleu);
}
.check {
  display: flex;
  gap: 10px;
  align-items: center;
  margin: 16px 0;
}
.check label {
  margin: 0;
  font-weight: 400;
}
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0 20px;
}
.send {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-top: 16px;
  background: var(--bleu) !important;
  border-radius: 6px;
}
.send:hover {
  background: #142e8f !important;
}
.send svg {
  width: 20px;
  height: 20px;
  fill: currentColor;
}
.filters {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(160px, 1fr) auto;
  gap: 0 16px;
  align-items: end;
  margin-bottom: 16px;
}
.filters label {
  margin-top: 0;
}
.search-button {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: var(--encre) !important;
  background: var(--jaune) !important;
  border-radius: 6px;
}
.search-button:hover {
  background: #f2c400 !important;
}
.accounts {
  list-style: none;
  margin: 0;
  padding: 0;
}
.accounts li {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 4px 16px;
  align-items: center;
  margin: 0;
  padding: 14px 4px;
  border-top: 1px solid var(--filet);
}
.accounts li > :not(.avatar):not(.actions) {
  grid-column: 2;
}
.accounts li > .actions {
  grid-column: 3;
  grid-row: 1 / span 2;
}
.accounts li > .dialog {
  grid-column: 1 / -1;
}
.accounts li.suspended {
  background: var(--creme-fonce);
}
.accounts li.pending {
  border-left: 4px solid var(--erreur);
  padding-left: 12px;
}
.avatar {
  grid-row: 1 / span 2;
  display: grid;
  place-items: center;
  width: 46px;
  height: 46px;
  border-radius: 50%;
  font-weight: 700;
  color: #142e8f;
  background: #dde4fb;
}
.avatar.client {
  color: var(--erreur-fonce);
  background: var(--erreur-fond);
}
h3 {
  margin: 0;
  font-size: 1.15rem;
  overflow-wrap: anywhere;
}
h4 {
  margin: 0 0 8px;
}
.badge {
  display: inline-block;
  margin-left: 8px;
  padding: 2px 10px;
  border-radius: 999px;
  background: #fff1a8;
  font-size: 0.85rem;
  font-weight: 600;
}
.badge.warn {
  background: var(--erreur-fond);
  color: var(--erreur-fonce);
}
.meta {
  margin: 0;
  overflow-wrap: anywhere;
}
.alert {
  margin: 4px 0 0;
  color: var(--erreur-fonce);
}
.actions {
  gap: 10px;
  margin: 0;
}
.actions button {
  min-height: 40px;
  padding: 8px 16px;
  border-radius: 6px;
}
.actions .secondary {
  border: 2px solid var(--nuit);
}
.danger-button {
  background: var(--rouge);
}
.danger-button:hover {
  background: var(--erreur-fonce);
}
@media (max-width: 800px) {
  .filters {
    grid-template-columns: minmax(0, 1fr);
    gap: 10px;
  }
  .accounts li {
    grid-template-columns: auto minmax(0, 1fr);
  }
  .accounts li > .actions {
    grid-column: 1 / -1;
    grid-row: auto;
  }
}
</style>
