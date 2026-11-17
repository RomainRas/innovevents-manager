<script setup>
// Suivi des devis (administratrice) : version courante de chaque devis, filtrable par statut.
import { ref, onMounted } from 'vue'
import { request } from './auth'

const rows = ref([]),
  status = ref(''),
  loading = ref(true),
  error = ref('')

// Libellés des statuts du devis et de l’événement associé.
const labels = {
  brouillon: 'Brouillon',
  etude_client: 'Étude côté client',
  modification: 'Modification demandée',
  accepte: 'Accepté',
  refuse: 'Refusé',
}

const events = {
  brouillon: 'brouillon',
  accepte: 'accepté',
  en_cours: 'en cours',
  termine: 'terminé',
  annule: 'annulé',
}

const money = v => (v === null ? '—' : Number(v).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' }))

// Recharge la liste à chaque changement du filtre.
async function load() {
  loading.value = true
  error.value = ''
  try {
    rows.value = (await request('/api/admin/quotes' + (status.value ? '?statut=' + status.value : ''))).devis
  } catch (e) {
    error.value = e.status === 403 ? 'Accès réservé à l’administratrice.' : 'Le suivi des devis est indisponible.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
<template>
  <main class="work-page">
    <p><RouterLink class="back-link" to="/espace">← Retour au tableau de bord</RouterLink></p>
    <h1 class="list-title" style="--bar: #d4a600">Suivi des devis</h1>
    <p>Version courante de chaque devis. Les demandes de modification et les devis acceptés apparaissent en premier.</p>
    <div class="search-bar">
      <div class="status-field">
        <label for="quote-status">Filtrer par statut</label>
        <select id="quote-status" v-model="status" @change="load">
          <option value="">Tous les statuts</option>
          <option v-for="(l, k) in labels" :key="k" :value="k">{{ l }}</option>
        </select>
      </div>
      <p v-if="!loading && !error" class="count">{{ rows.length }} devis</p>
    </div>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <p v-if="!loading && !error && !rows.length">Aucun devis pour ce filtre.</p>
    <ul v-if="!loading && !error" class="quotes">
      <li v-for="r in rows" :key="r.reference" :class="r.statut">
        <RouterLink :to="'/evenements/' + r.evenement_id + '/devis'">{{ r.reference }} — {{ r.evenement }}</RouterLink
        ><br />
        <span class="client"
          >{{ r.client }} · <span class="version">version {{ r.version }}</span> ·
          <strong>{{ money(r.total_ttc) }}</strong></span
        ><br />
        <span class="badge" :class="r.statut">{{ labels[r.statut] }}</span>
        <small>Événement {{ events[r.evenement_statut] || r.evenement_statut }}</small>
      </li>
    </ul>
  </main>
</template>
<style scoped>
/* Suivi des devis : filtre et compteur, une carte par devis avec liseré selon le statut. */
.search-bar {
  justify-content: space-between;
}
.status-field {
  flex: 0 1 340px !important;
}
.count {
  margin: 0;
  font-weight: 700;
}
.quotes {
  margin: 0;
  padding: 0;
  list-style: none;
}
.quotes li {
  margin: 0 0 12px;
  padding: 14px 20px;
  line-height: 1.8;
  background: var(--creme);
  border: 1px solid var(--filet);
  border-left: 5px solid #b9b09c;
  border-radius: 8px;
}
.quotes li.modification,
.quotes li.refuse {
  border-left-color: var(--rouge);
}
.quotes li.accepte {
  border-left-color: var(--succes);
}
.quotes li.etude_client {
  border-left-color: #d4a600;
}
.quotes a {
  color: var(--encre);
  font-weight: 800;
  font-size: 1.1rem;
}
.client {
  color: #333;
}
.version {
  padding: 1px 8px;
  border-radius: 6px;
  background: var(--filet-clair);
  font-size: 0.9rem;
}
.badge {
  display: inline-block;
  padding: 2px 12px;
  border-radius: 999px;
  background: #fff1a8;
  font-weight: 600;
}
.badge.modification,
.badge.refuse {
  background: var(--erreur-fond);
  color: var(--erreur-fonce);
}
.badge.accepte {
  background: #d9f0e1;
  color: var(--succes);
}
</style>
