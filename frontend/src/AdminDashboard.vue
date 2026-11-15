<script setup>
// Tableau de bord de l'administratrice (dans l'espace personnel) : chiffres clés, prochains événements et dernières demandes.
import { onMounted, ref } from 'vue'
import { request } from './auth'

const data = ref(null),
  error = ref(''),
  loading = ref(true)
const statuses = { brouillon: 'Brouillon', accepte: 'Accepté', en_cours: 'En cours', termine: 'Terminé' }

// Date locale « AAAA-MM-JJTHH:MM » → « JJ/MM/AAAA à HH h MM ».
const local = v => {
  const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(v || '')
  return m ? `${m[3]}/${m[2]}/${m[1]} à ${m[4]} h ${m[5]}` : ''
}

// Horodatage UTC de la base → date de Paris.
const day = v =>
  v ? new Date(v.replace(' ', 'T').slice(0, 19) + 'Z').toLocaleDateString('fr-FR', { timeZone: 'Europe/Paris' }) : ''

onMounted(async () => {
  try {
    data.value = await request('/api/admin/dashboard')
  } catch {
    error.value = 'Le tableau de bord est indisponible pour le moment.'
  } finally {
    loading.value = false
  }
})
</script>
<template>
  <section aria-labelledby="dashboard-title" class="dashboard">
    <h2 id="dashboard-title">Tableau de bord</h2>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <template v-if="data">
      <section aria-labelledby="kpi-title" class="widget">
        <h3 id="kpi-title">Indicateurs clés</h3>
        <dl class="kpis">
          <div>
            <dt>Clients actifs (projet en cours)</dt>
            <dd>{{ data.indicateurs.clients_actifs }}</dd>
          </div>
          <div>
            <dt>Événements en brouillon à traiter</dt>
            <dd>{{ data.indicateurs.evenements_brouillon }}</dd>
          </div>
          <div>
            <dt>Avis à modérer</dt>
            <dd>{{ data.indicateurs.avis_a_moderer }}</dd>
            <RouterLink v-if="data.indicateurs.avis_a_moderer" to="/moderation-avis">Modérer</RouterLink>
          </div>
          <div>
            <dt>Demandes de suppression de compte</dt>
            <dd>{{ data.indicateurs.demandes_suppression }}</dd>
            <RouterLink v-if="data.indicateurs.demandes_suppression" to="/comptes">Traiter</RouterLink>
          </div>
        </dl>
      </section>
      <div class="columns">
        <section aria-labelledby="upcoming-title" class="widget">
          <h3 id="upcoming-title">Prochains événements</h3>
          <p v-if="!data.prochains_evenements.length">Aucun événement à venir.</p>
          <ul class="plain">
            <li v-for="e in data.prochains_evenements" :key="e.id">
              <RouterLink :to="'/evenements/' + e.id">{{ e.nom }}</RouterLink>
              <span class="meta">{{ e.client }} — {{ local(e.debut) }} — {{ statuses[e.statut] || e.statut }}</span>
            </li>
          </ul>
        </section>
        <section aria-labelledby="notes-title" class="widget">
          <h3 id="notes-title">Notes récentes</h3>
          <p v-if="!data.notes_recentes.length">Aucune note n’a encore été ajoutée sur les projets.</p>
          <ul class="plain">
            <li v-for="n in data.notes_recentes" :key="n.id">
              <p class="note">{{ n.contenu }}</p>
              <span class="meta">{{ n.auteur }} — {{ n.evenement }} — {{ day(n.cree_le) }}</span>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </section>
</template>
<style scoped>
.widget {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 16px;
  margin: 16px 0;
}
h3 {
  margin: 0 0 12px;
  font-size: 1.15rem;
}
.kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 12px;
  margin: 0;
}
.kpis div {
  background: #eef3f6;
  border-radius: 6px;
  padding: 12px;
}
dt {
  font-size: 0.95rem;
  color: #2f4250;
}
dd {
  margin: 4px 0;
  font-size: 1.8rem;
  font-weight: 700;
  color: #142d43;
}
.columns {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: 0 16px;
}
.plain {
  list-style: none;
  padding: 0;
  margin: 0;
}
.plain li {
  padding: 8px 0;
  border-bottom: 1px solid #e2e8f0;
  overflow-wrap: anywhere;
}
.plain li:last-child {
  border-bottom: 0;
}
.meta {
  display: block;
  font-size: 0.95rem;
  color: #4a5b68;
}
.note {
  margin: 0 0 4px;
  white-space: pre-line;
}
</style>
