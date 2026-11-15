<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { request } from './auth'
import { eventStatuses, localDate } from './teamFormat'
const route = useRoute(), data = ref(null), error = ref(''), loading = ref(true)
onMounted(async () => {
  try { data.value = await request('/api/team/client?id=' + encodeURIComponent(route.params.id)) }
  catch (e) { error.value = e.status === 404 || e.status === 422 ? 'Ce client n’existe pas.' : 'La fiche client est indisponible pour le moment.' }
  finally { loading.value = false }
})
</script>
<template>
  <main class="panel wide">
    <p><RouterLink to="/equipe/clients">← Tous les clients</RouterLink></p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <template v-if="data">
      <h1>{{ data.client.entreprise }}</h1>
      <dl class="facts">
        <div><dt>Contact</dt><dd>{{ data.client.contact }}</dd></div>
        <div><dt>Téléphone</dt><dd><a :href="'tel:' + data.client.telephone">{{ data.client.telephone }}</a></dd></div>
        <div><dt>Adresse électronique</dt><dd><a :href="'mailto:' + data.client.email">{{ data.client.email }}</a></dd></div>
        <div v-if="data.client.adresse.length"><dt>Adresse</dt><dd><span v-for="(l, i) in data.client.adresse" :key="i">{{ l }}<br></span></dd></div>
        <div><dt>Espace client</dt><dd>{{ data.client.compte ? 'Compte ouvert' : 'Pas de compte' }}</dd></div>
      </dl>
      <h2>Historique des événements</h2>
      <p v-if="!data.evenements.length">Aucun événement pour ce client.</p>
      <table v-else>
        <caption class="sr-only">Événements du client, du plus récent au plus ancien</caption>
        <thead><tr><th scope="col">Événement</th><th scope="col">Date</th><th scope="col">Lieu</th><th scope="col">Statut</th></tr></thead>
        <tbody><tr v-for="e in data.evenements" :key="e.id">
          <td><RouterLink :to="'/equipe/evenements/' + e.id">{{ e.nom }}</RouterLink></td><td>{{ localDate(e.debut, false) }}</td><td>{{ e.lieu || '—' }}</td><td>{{ eventStatuses[e.statut] || e.statut }}</td>
        </tr></tbody>
      </table>
    </template>
  </main>
</template>
<style scoped>
.facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin: 16px 0 }
.facts div { background: #EEF3F6; border-radius: 6px; padding: 12px }
dt { font-size: .9rem; color: #2F4250 } dd { margin: 4px 0 0; font-weight: 600; overflow-wrap: anywhere }
table { width: 100%; border-collapse: collapse } th, td { text-align: left; padding: 8px 6px; border-bottom: 1px solid #E2E8F0; overflow-wrap: anywhere }
@media (max-width: 600px) { thead { display: none } tr { display: block; border-bottom: 1px solid #CBD5E1; padding: 8px 0 } td { display: block; border: 0; padding: 2px 0 } }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap }
</style>
