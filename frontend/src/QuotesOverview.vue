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
  <main class="panel">
    <h1>Suivi des devis</h1>
    <p>Version courante de chaque devis. Les demandes de modification et les devis acceptés apparaissent en premier.</p>
    <label for="quote-status">Filtrer par statut</label>
    <select id="quote-status" v-model="status" @change="load">
      <option value="">Tous les statuts</option>
      <option v-for="(l, k) in labels" :key="k" :value="k">{{ l }}</option>
    </select>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <p v-if="!loading && !error && !rows.length">Aucun devis pour ce filtre.</p>
    <ul v-if="!loading && !error" class="quotes">
      <li v-for="r in rows" :key="r.reference">
        <RouterLink :to="'/evenements/' + r.evenement_id + '/devis'">{{ r.reference }} — {{ r.evenement }}</RouterLink
        ><br />
        {{ r.client }} · version {{ r.version }} · {{ money(r.total_ttc) }}<br />
        <span class="badge" :class="r.statut">{{ labels[r.statut] }}</span> <small>Événement {{ events[r.evenement_statut] || r.evenement_statut }}</small>
      </li>
    </ul>
  </main>
</template>
<style scoped>
select {
  width: 100%;
  min-height: 48px;
  padding: 12px;
  border: 1px solid #718391;
  border-radius: 4px;
  font: inherit;
  background: white;
}
.quotes {
  padding: 0;
  list-style: none;
}
.quotes li {
  border-bottom: 1px solid #e2e8f0;
  padding-bottom: 16px;
}
.badge {
  display: inline-block;
  padding: 2px 10px;
  border-radius: 999px;
  background: #e6eef3;
  font-weight: 600;
}
.badge.modification {
  background: #fef0c7;
  color: #7a2e0e;
}
.badge.accepte {
  background: #d1fadf;
  color: #05603a;
}
.badge.refuse {
  background: #fee4e2;
  color: #912018;
}
</style>
