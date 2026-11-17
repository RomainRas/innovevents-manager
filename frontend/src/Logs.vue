<script setup>
// Journaux d'activité (administratrice) : dernières actions enregistrées dans MongoDB.
import { onMounted, ref } from 'vue'
import { request } from './auth'

const rows = ref([]),
  error = ref(''),
  loading = ref(true)

// Couleur de l'étiquette d'action : échec en rouge, réussite en vert, autre action en jaune.
const tone = type => (/ECHOU|REFUS|ERREUR/.test(type) ? 'red' : /REUSSI|VALID/.test(type) ? 'green' : 'yellow')

onMounted(async () => {
  try {
    rows.value = (await request('/api/admin/logs')).logs
  } catch (e) {
    error.value = e.status === 403 ? 'Vous n’avez pas accès aux journaux.' : 'Les journaux sont indisponibles.'
  } finally {
    loading.value = false
  }
})
</script>
<template>
  <main class="work-page">
    <p><RouterLink class="back-link" to="/espace">← Retour au tableau de bord</RouterLink></p>
    <!-- Carte : titre, compteur, filet tricolore puis tableau des actions -->
    <div class="work-card logs-card">
      <div class="logs-head">
        <div>
          <h1 class="logs-title">Journaux d’actions</h1>
          <p class="lead">Les vingt dernières actions enregistrées.</p>
        </div>
        <p v-if="!loading && !error" class="counter">
          <svg aria-hidden="true" viewBox="0 0 24 24">
            <ellipse cx="12" cy="5" rx="8" ry="3" />
            <path d="M4 5v14c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 12c0 1.7 3.6 3 8 3s8-1.3 8-3" /></svg
          >{{ rows.length }} dernière{{ rows.length > 1 ? 's' : '' }} action{{ rows.length > 1 ? 's' : '' }}
        </p>
      </div>
      <p v-if="loading" role="status">Chargement…</p>
      <p role="alert" class="error">{{ error }}</p>
      <table v-if="rows.length">
        <caption class="sr-only">
          Dernières actions : date, type d’action et utilisateur
        </caption>
        <thead>
          <tr>
            <th scope="col">Date et heure</th>
            <th scope="col">Action</th>
            <th scope="col">Utilisateur</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="index">
            <td>{{ new Date(row.at).toLocaleString('fr-FR') }}</td>
            <td>
              <span class="action" :class="tone(row.type)">{{ row.type }}</span>
            </td>
            <td>Utilisateur : {{ row.actor || 'Non identifié' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </main>
</template>
<style scoped>
/* Journaux : en-tête à barre rouge, filet tricolore, tableau zébré, étiquettes d'action colorées. */
.logs-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 12px 24px;
  padding-bottom: 18px;
  border-bottom: 6px solid;
  border-image: linear-gradient(90deg, var(--rouge) 0 37%, var(--bleu) 37% 67%, var(--jaune) 67%) 1;
}
.logs-title {
  margin: 0;
  padding-left: 22px;
  border-left: 10px solid var(--rouge);
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: clamp(34px, 4.5vw, 52px);
  line-height: 1.1;
  text-transform: uppercase;
  color: var(--encre);
}
.lead {
  margin: 4px 0 0 32px;
  font-size: 1.1rem;
}
.counter {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin: 0;
  padding: 10px 16px;
  border-radius: 6px;
  background: var(--creme-fonce);
}
.counter svg {
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
}
table {
  width: 100%;
  margin-top: 20px;
  border-collapse: collapse;
}
thead th {
  padding: 12px 16px;
  text-align: left;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: 1.15rem;
  text-transform: uppercase;
  background: var(--creme-fonce);
}
td {
  padding: 8px 16px;
  font-variant-numeric: tabular-nums;
  overflow-wrap: anywhere;
}
tbody tr:nth-child(even) {
  background: var(--papier);
}
.action {
  display: inline-block;
  padding: 2px 12px;
  border-radius: 4px;
  font-size: 0.85rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  color: var(--encre);
  background: var(--jaune);
}
.action.green {
  color: white;
  background: #0b6b5a;
}
.action.red {
  color: white;
  background: var(--erreur);
}
.error:empty {
  display: none;
}
@media (max-width: 640px) {
  thead {
    display: none;
  }
  tr {
    display: grid;
    gap: 4px;
    padding: 10px 0;
    border-bottom: 1px solid var(--filet);
  }
  td {
    padding: 0 8px;
  }
  .lead {
    margin-left: 0;
  }
}
</style>
