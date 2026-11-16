<script setup>
// Tableau de bord de l'administratrice (dans l'espace personnel) : chiffres clés, prochains événements et dernières notes de l'équipe.
import { onMounted, ref } from 'vue'
import { request } from './auth'
import Icon from './Icon.vue'

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

// Pavé de date d'un événement : « 3 déc. » puis l'année.
const dayParts = v => {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v || '')
  if (!m) return ['', '']
  const d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]))
  return [d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', timeZone: 'UTC' }), m[1]]
}

// Couleur de la pastille de statut.
const tone = { brouillon: 'blue', accepte: 'green', en_cours: 'yellow', termine: 'grey' }

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
    <h2 id="dashboard-title" class="slash-title yellow">Tableau de bord</h2>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <template v-if="data">
      <!-- Chiffres clés sur cartes sombres : clients actifs, brouillons, avis à modérer, demandes de suppression -->
      <section aria-labelledby="kpi-title">
        <h3 id="kpi-title" class="sr-only">Indicateurs clés</h3>
        <dl class="kpis">
          <div class="red">
            <Icon name="users" />
            <dt>Clients actifs (projet en cours)</dt>
            <dd>{{ data.indicateurs.clients_actifs }}</dd>
          </div>
          <div class="blue">
            <Icon name="calendar" />
            <dt>Événements en brouillon à traiter</dt>
            <dd>{{ data.indicateurs.evenements_brouillon }}</dd>
          </div>
          <div class="yellow">
            <Icon name="star" />
            <dt>
              Avis à modérer
              <RouterLink v-if="data.indicateurs.avis_a_moderer" to="/moderation-avis">Modérer</RouterLink>
            </dt>
            <dd>{{ data.indicateurs.avis_a_moderer }}</dd>
          </div>
          <div class="red">
            <Icon name="trash" />
            <dt>
              Demandes de suppression de compte
              <RouterLink v-if="data.indicateurs.demandes_suppression" to="/comptes">Traiter</RouterLink>
            </dt>
            <dd>{{ data.indicateurs.demandes_suppression }}</dd>
          </div>
        </dl>
      </section>
      <div class="columns">
        <!-- Prochains événements : pavé de date, nom, client, statut -->
        <section aria-labelledby="upcoming-title" class="widget">
          <div class="widget-head">
            <h3 id="upcoming-title" class="slash-title blue">Prochains événements</h3>
            <RouterLink to="/equipe/evenements">Voir tous les événements <span aria-hidden="true">→</span></RouterLink>
          </div>
          <p v-if="!data.prochains_evenements.length">Aucun événement à venir.</p>
          <ul class="plain">
            <li v-for="e in data.prochains_evenements" :key="e.id">
              <span class="date-tile"
                ><Icon name="calendar" /><span>{{ dayParts(e.debut)[0] }}<br />{{ dayParts(e.debut)[1] }}</span></span
              >
              <span class="item">
                <RouterLink :to="'/evenements/' + e.id">{{ e.nom }}</RouterLink>
                <span class="meta">Client : {{ e.client }} — {{ local(e.debut) }}</span>
              </span>
              <span class="pill" :class="tone[e.statut]">{{ statuses[e.statut] || e.statut }}</span>
            </li>
          </ul>
        </section>
        <!-- Dernières notes de l'équipe -->
        <section aria-labelledby="notes-title" class="widget">
          <div class="widget-head">
            <h3 id="notes-title" class="slash-title">Notes récentes</h3>
            <RouterLink to="/equipe/evenements">Voir toutes les notes <span aria-hidden="true">→</span></RouterLink>
          </div>
          <p v-if="!data.notes_recentes.length">Aucune note n’a encore été ajoutée sur les projets.</p>
          <ul class="plain">
            <li v-for="n in data.notes_recentes" :key="n.id">
              <span class="note-tile"><Icon name="note" /></span>
              <span class="item">
                <p class="note">{{ n.contenu }}</p>
                <span class="meta">{{ n.evenement }} — {{ day(n.cree_le) }} — par {{ n.auteur }}</span>
              </span>
            </li>
          </ul>
        </section>
      </div>
    </template>
  </section>
</template>
<style scoped>
/* Chiffres clés : cartes sombres, liseré coloré, pictogramme, libellé et grand nombre. */
.kpis {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 16px;
  margin: 0;
}
.kpis div {
  position: relative;
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 16px;
  align-items: center;
  padding: 18px 20px 18px 26px;
  color: white;
  background: #172033;
  border-radius: 6px;
  overflow: hidden;
}
.kpis div::before {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  left: 0;
  width: 8px;
  background: var(--accent, #c8221a);
}
.kpis .blue {
  --accent: #1d3fbf;
}
.kpis .yellow {
  --accent: #ffd836;
}
.kpis :deep(.icon) {
  width: 34px;
  height: 34px;
}
dt {
  font-size: 1rem;
  line-height: 1.3;
}
dt a {
  display: block;
  color: #ffd836;
  font-size: 0.9rem;
}
dd {
  margin: 0;
  padding-left: 18px;
  border-left: 1px solid #8a93a5;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-size: 2.6rem;
  line-height: 1;
}
.columns {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 20px;
  margin-top: 24px;
}
.widget {
  padding: 8px 20px 12px;
  background: #fbf8f1;
  border: 1px solid #d8cfbd;
  border-radius: 8px;
}
.widget-head {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 4px 16px;
  border-bottom: 1px solid #d8cfbd;
}
.widget-head .slash-title {
  margin: 12px 0;
  font-size: 1.9rem;
}
.widget-head a {
  color: #1d3fbf;
  font-size: 0.95rem;
}
.plain {
  list-style: none;
  padding: 0;
  margin: 0;
}
.plain li {
  display: flex;
  align-items: center;
  gap: 16px;
  margin: 0;
  padding: 12px 0;
  border-bottom: 1px solid #e5dccb;
  overflow-wrap: anywhere;
}
.plain li:last-child {
  border-bottom: 0;
}
.date-tile,
.note-tile {
  flex: none;
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  background: #efe8d8;
  border-radius: 6px;
  font-weight: 700;
  line-height: 1.2;
}
.item {
  flex: 1;
  min-width: 0;
}
.item a {
  color: #111;
  font-weight: 700;
}
.meta {
  display: block;
  font-size: 0.95rem;
  color: #4a4a4a;
}
.note {
  margin: 0 0 4px;
  font-weight: 700;
  white-space: pre-line;
}
.pill {
  flex: none;
  padding: 3px 10px;
  border-radius: 6px;
  font-size: 0.9rem;
  font-weight: 600;
  background: #dde4fb;
  color: #142e8f;
}
.pill.yellow {
  background: #fff1a8;
  color: #111;
}
.pill.green {
  background: #d9f0e1;
  color: #0b6b3a;
}
.pill.grey {
  background: #e5dccb;
  color: #333;
}
@media (max-width: 600px) {
  .plain li {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
  }
  .pill {
    grid-column: 2;
    justify-self: start;
  }
}
</style>
