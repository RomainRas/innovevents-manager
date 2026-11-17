<script setup>
// Page publique d'un événement : seuls les événements dont le client a accepté la publication sont renvoyés.
import { onMounted, ref } from 'vue'
import Icon from './Icon.vue'
import { useRoute } from 'vue-router'
import { request } from './auth'
import { period, statusLabels } from './publicFormat'

const route = useRoute(),
  event = ref(null),
  error = ref(''),
  loading = ref(true)

onMounted(async () => {
  try {
    event.value = (await request('/api/public/event?id=' + encodeURIComponent(route.params.id))).event
    document.title = event.value.nom + ' — Innov’Events'
  } catch (e) {
    error.value =
      e.status === 404
        ? 'Cet événement n’existe pas ou n’est pas publié.'
        : 'L’événement est momentanément indisponible.'
  } finally {
    loading.value = false
  }
})
</script>
<template>
  <main class="poster-page event-page">
    <!-- Décor : aplats déchirés bleu et jaune -->
    <span class="shape blue torn-a deco-a" aria-hidden="true"></span>
    <span class="shape yellow torn-b deco-b" aria-hidden="true"></span>
    <p><RouterLink class="back" to="/evenements-publics">← Tous les événements</RouterLink></p>
    <p v-if="loading">Chargement…</p>
    <template v-else-if="event">
      <article class="event-detail">
        <div class="main-col">
          <h1>{{ event.nom }}</h1>
          <img
            v-if="event.image"
            :src="`/api/public/event-image?id=${event.id}`"
            :alt="event.image_alternative || ''"
            width="1200"
            height="750"
          />
        </div>
        <!-- Fiche pratique : chaque information précédée d'un pictogramme sur pastille colorée -->
        <dl class="facts">
          <div>
            <span class="tile red"><Icon name="calendar" /></span>
            <dt>Dates</dt>
            <dd>{{ period(event.debut, event.fin) }}</dd>
          </div>
          <div>
            <span class="tile blue"><Icon name="pin" /></span>
            <dt>Lieu</dt>
            <dd>{{ event.lieu }}</dd>
          </div>
          <div>
            <span class="tile yellow"><Icon name="users" /></span>
            <dt>Type d’événement</dt>
            <dd>{{ event.type_evenement }}</dd>
          </div>
          <div v-if="event.theme">
            <span class="tile red"><Icon name="tag" /></span>
            <dt>Thème</dt>
            <dd>{{ event.theme }}</dd>
          </div>
          <div v-if="event.participants">
            <span class="tile blue"><Icon name="user" /></span>
            <dt>Participants</dt>
            <dd>{{ event.participants }}</dd>
          </div>
          <div>
            <span class="tile yellow"><Icon name="flag" /></span>
            <dt>Statut</dt>
            <dd>
              <span class="pill">{{ statusLabels[event.statut] }}</span>
            </dd>
          </div>
        </dl>
      </article>
      <!-- Invitation à demander un devis, sur bande rouge déchirée -->
      <section class="quote-band" aria-labelledby="quote-title">
        <h2 id="quote-title">Prestations et Devis</h2>
        <p>Vous imaginez un événement similaire ? Décrivez votre projet et recevez un devis personnalisé.</p>
        <RouterLink class="band-button" to="/demande-devis">Demander un devis</RouterLink>
      </section>
    </template>
    <p v-else class="error" role="alert">{{ error }}</p>
  </main>
</template>
<style scoped>
/* Fiche publique d'un événement en style affiche. */
.event-page {
  padding-inline: max(16px, calc((100% - 1200px) / 2));
}
.back {
  color: var(--encre);
  font-weight: 700;
}
.event-detail {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(280px, 1fr);
  gap: 40px;
  align-items: end;
  margin-top: 16px;
}
h1 {
  margin: 0 0 28px;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: clamp(48px, 6.5vw, 92px);
  line-height: 0.98;
  text-transform: uppercase;
  color: var(--encre);
}
h1::after {
  content: '';
  display: block;
  width: 240px;
  max-width: 60%;
  height: 9px;
  margin-top: 14px;
  background: var(--jaune);
  transform: rotate(-1.5deg);
}
.main-col img {
  display: block;
  width: 100%;
  height: auto;
  aspect-ratio: 16 / 10;
  object-fit: cover;
  border-radius: 0;
}
.facts {
  display: block;
  margin: 0;
  padding: 8px 28px;
  border: 2px solid var(--encre);
  background: var(--creme);
}
.facts div {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0 18px;
  align-items: center;
  padding: 16px 0;
  border-top: 1px solid var(--encre);
}
.facts div:first-child {
  border-top: 0;
}
.tile {
  grid-row: span 2;
  display: grid;
  place-items: center;
  width: 58px;
  height: 52px;
  clip-path: polygon(4% 8%, 96% 0, 100% 92%, 0 100%);
}
.tile.red {
  background: #d9342b;
  color: var(--encre);
}
.tile.blue {
  background: var(--bleu);
  color: var(--encre);
}
.tile.yellow {
  background: var(--jaune);
  color: var(--encre);
}
.tile :deep(.icon) {
  width: 30px;
  height: 30px;
  stroke-width: 2.2;
}
dt {
  font-weight: 800;
  font-size: 1.1rem;
}
dd {
  margin: 0;
  overflow-wrap: anywhere;
}
.pill {
  display: inline-block;
  padding: 2px 14px;
  border-radius: 999px;
  background: var(--jaune);
  font-weight: 600;
}
.quote-band {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr) auto;
  gap: 24px 40px;
  align-items: center;
  margin-top: 56px;
  padding: 40px clamp(20px, 5vw, 64px);
  color: white;
  background: #d9342b;
  clip-path: polygon(0 4%, 20% 0, 48% 5%, 76% 1%, 100% 6%, 99% 92%, 74% 100%, 46% 95%, 20% 100%, 1% 94%);
}
.quote-band h2 {
  margin: 0;
  max-width: 7em;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: clamp(40px, 5vw, 64px);
  line-height: 1;
  text-transform: uppercase;
  color: white;
  transform: rotate(-2deg);
}
.quote-band p {
  margin: 0;
  padding-left: 32px;
  border-left: 2px solid white;
  font-size: 1.15rem;
}
.band-button {
  display: inline-block;
  padding: 14px 26px;
  color: var(--encre);
  background: var(--jaune);
  font-family: var(--police-titre);
  font-size: 1.5rem;
  text-decoration: none;
  box-shadow: 4px 4px 0 var(--encre);
  transform: rotate(-1deg);
}
.band-button:hover {
  transform: rotate(0) translate(-2px, -2px);
}
.deco-a {
  top: 110px;
  right: -60px;
  width: 260px;
  height: 160px;
  transform: rotate(-12deg);
}
.deco-b {
  top: 760px;
  left: -60px;
  width: 200px;
  height: 120px;
  transform: rotate(8deg);
}
@media (max-width: 900px) {
  .event-detail,
  .quote-band {
    grid-template-columns: minmax(0, 1fr);
  }
  .quote-band p {
    padding-left: 0;
    border-left: 0;
  }
}
</style>
