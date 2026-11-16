<script setup>
// Liste des demandes de devis (administratrice) : filtre par statut, recherche et pagination par curseur.
import { onMounted, ref } from 'vue'
import Icon from './Icon.vue'
import { request } from './auth'

const status = ref('a_contacter'),
  search = ref(''),
  cursor = ref(''),
  next = ref(null),
  rows = ref([]),
  busy = ref(false),
  error = ref('')
// Couleur de la pastille selon le statut de la demande.
const tones = { a_contacter: '', echoue: 'red', converti: 'green' }
const labels = { a_contacter: 'À contacter', echoue: 'Échoué', converti: 'Converti' }

// Charge une page de résultats ; reset repart du début (nouveau filtre ou nouvelle recherche).
async function load(reset = false) {
  if (reset) cursor.value = ''
  busy.value = true
  error.value = ''
  rows.value = []
  next.value = null
  try {
    const data = await request(
      '/api/admin/prospects?' +
        new URLSearchParams({ status: status.value, search: search.value, before: cursor.value }),
    )
    rows.value = data.prospects
    next.value = data.next
  } catch (e) {
    error.value =
      e.status === 403
        ? 'Cet espace est réservé à l’administratrice.'
        : e.status === 401
          ? 'Votre session a expiré. Reconnectez-vous.'
          : 'Chargement impossible. Réessayez.'
  } finally {
    busy.value = false
  }
}

// Page suivante : le curseur est l'identifiant de la dernière demande affichée.
function more() {
  cursor.value = next.value
  load()
}

onMounted(() => load())
</script>
<template>
  <main class="work-page">
    <p><RouterLink class="back-link" to="/espace">← Retour à mon espace</RouterLink></p>
    <h1 class="list-title">Demandes reçues</h1>
    <p>Retrouvez les prospects à contacter pour préciser leur besoin et vérifier la faisabilité du projet.</p>
    <!-- Filtre par statut et recherche -->
    <form @submit.prevent="load(true)" class="search-bar">
      <div class="search-field">
        <label for="prospect-search">Entreprise, nom ou adresse électronique</label
        ><input id="prospect-search" v-model="search" maxlength="200" type="search" :disabled="busy" /><Icon
          name="search"
        />
      </div>
      <div class="status-field">
        <label for="prospect-status">Statut</label
        ><select id="prospect-status" v-model="status" :disabled="busy">
          <option value="a_contacter">À contacter</option>
          <option value="echoue">Échoué</option>
          <option value="converti">Converti</option>
          <option value="">Tous</option>
        </select>
      </div>
      <button :disabled="busy">Rechercher</button>
    </form>
    <p v-if="busy" role="status">Chargement…</p>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <p v-if="!busy && !error && !rows.length" role="status">Aucune demande ne correspond à ces critères.</p>
    <!-- Demandes reçues, les plus récentes en premier -->
    <ul class="result-list">
      <li v-for="row in rows" :key="row.id">
        <span class="result-tile"><Icon name="building" /></span>
        <div class="result-main">
          <h2>
            <RouterLink :to="'/prospects/' + row.id">{{ row.entreprise }}</RouterLink>
          </h2>
          <p class="result-meta">
            {{ row.prenom }} {{ row.nom }}<span class="sr-only"> — {{ labels[row.statut] }}</span>
          </p>
        </div>
        <div class="details">
          <span class="status-pill" :class="tones[row.statut]" aria-hidden="true">{{ labels[row.statut] }}</span>
          <p class="result-meta">
            {{ row.type_evenement }} · Date souhaitée : {{ row.date_souhaitee.split('-').reverse().join('/') }}
          </p>
        </div>
        <!-- Indication visuelle : toute la carte ouvre la fiche, via le lien du titre -->
        <span class="open-link" aria-hidden="true">Ouvrir la demande <Icon name="arrow" /></span>
      </li>
    </ul>
    <!-- Page suivante -->
    <div class="pagination">
      <button v-if="cursor" :disabled="busy" @click="load(true)">Revenir aux premières demandes</button
      ><button v-if="next" :disabled="busy" @click="more">Demandes suivantes</button>
    </div>
  </main>
</template>
<style scoped>
.status-field {
  flex: 0 1 260px !important;
}
.details {
  flex: 1 1 260px;
}
.details .result-meta {
  margin-top: 6px;
}
.pagination {
  display: flex;
  gap: 16px;
  flex-wrap: wrap;
}
</style>
