<script setup>
// Page publique des avis clients : seuls les avis validés par l'équipe sont renvoyés par l'API.
import { onMounted, ref } from 'vue'
import { request } from './auth'

const reviews = ref([]),
  error = ref(''),
  loading = ref(true)
const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeZone: 'UTC' })

// Couleurs des rubans de papier collés sur les cartes, en alternance.
const tapes = ['red', 'blue', 'yellow', 'red']

onMounted(async () => {
  try {
    reviews.value = (await request('/api/public/reviews')).reviews
  } catch {
    error.value = 'Les avis sont momentanément indisponibles.'
  } finally {
    loading.value = false
  }
})
</script>
<template>
  <main class="poster-page reviews-page">
    <!-- Décor : aplats déchirés rouge et bleu -->
    <span class="shape red torn-a deco-a" aria-hidden="true"></span>
    <span class="shape blue torn-b deco-b" aria-hidden="true"></span>
    <h1 class="reviews-title">Avis de nos clients</h1>
    <p class="poster-lead">
      Les avis sont déposés par nos clients depuis leur espace après leur événement, puis vérifiés par notre équipe
      avant publication.
    </p>
    <p v-if="loading">Chargement des avis…</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="!reviews.length">Aucun avis publié pour le moment.</p>
    <template v-else>
      <p class="count">
        <span>{{ reviews.length }} avis vérifié{{ reviews.length > 1 ? 's' : '' }}</span>
      </p>
      <ul class="reviews">
        <li v-for="(r, i) in reviews" :key="r.id" :class="tapes[i % tapes.length]">
          <!-- Photo de l'événement publié, puis la citation et sa source -->
          <img
            v-if="r.image"
            :src="`/api/public/event-image?id=${r.evenement_id}`"
            :alt="'Photo de l’événement ' + r.evenement"
            width="1200"
            height="750"
            loading="lazy"
          />
          <figure>
            <blockquote>
              <p>{{ r.contenu }}</p>
            </blockquote>
            <figcaption>
              <strong>{{ r.auteur }}</strong> — {{ r.evenement }} ({{ r.type_evenement }}), publié le
              <time :datetime="r.publie_le">{{ dateFormat.format(new Date(r.publie_le + 'T00:00:00Z')) }}</time>
            </figcaption>
          </figure>
        </li>
      </ul>
    </template>
  </main>
</template>
<style scoped>
/* Page Avis en style affiche : grand titre, compteur sur bande bleue, cartes photo + citation. */
.reviews-page {
  padding-inline: max(16px, calc((100% - 1200px) / 2));
}
.reviews-title {
  margin: 0 0 8px;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-weight: 400;
  font-size: clamp(52px, 8vw, 104px);
  line-height: 1;
  text-transform: uppercase;
  color: #111;
}
.reviews-title::after {
  content: '';
  display: block;
  width: 260px;
  max-width: 50%;
  height: 8px;
  margin-top: 6px;
  background: #ffd836;
  transform: rotate(-1deg);
}
.reviews-page .poster-lead {
  font-weight: 400;
}
.count {
  display: flex;
  align-items: center;
  gap: 16px;
  max-width: 520px;
  margin: 24px 0 40px;
  font-weight: 700;
  font-size: 1.2rem;
}
.count span {
  padding: 6px 16px;
  color: white;
  background: #1d3fbf;
  transform: rotate(-2deg);
}
.count::after {
  content: '';
  flex: 1;
  border-top: 2px solid #111;
}
.reviews {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 48px 40px;
  margin: 0;
  padding: 0;
  list-style: none;
}
.reviews li {
  position: relative;
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  gap: 20px;
  margin: 0;
  padding: 16px;
  border: 0;
  border-radius: 0;
  background: #fbf8f1;
  box-shadow: 0 3px 0 #d8cfbd;
}
/* Rubans de papier collés en haut à gauche et en bas à droite. */
.reviews li::before,
.reviews li::after {
  content: '';
  position: absolute;
  width: 150px;
  height: 18px;
  background: var(--tape);
}
.reviews li::before {
  top: -12px;
  left: -10px;
  transform: rotate(-3deg);
}
.reviews li::after {
  right: 14px;
  bottom: -10px;
  transform: rotate(-2deg);
  background: var(--tape2);
}
.reviews li.red {
  --tape: #c8221a;
  --tape2: #ffd836;
}
.reviews li.blue {
  --tape: #1d3fbf;
  --tape2: #c8221a;
}
.reviews li.yellow {
  --tape: #ffd836;
  --tape2: #1d3fbf;
}
.reviews img {
  width: 100%;
  height: 100%;
  min-height: 220px;
  object-fit: cover;
}
figure {
  display: flex;
  flex-direction: column;
  margin: 0;
}
blockquote {
  margin: 0;
}
blockquote::before {
  content: '“';
  display: block;
  height: 40px;
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 4rem;
  font-weight: 700;
  line-height: 1;
  color: var(--tape);
}
.reviews li.yellow blockquote::before {
  color: #b38a00;
}
blockquote p {
  margin: 0;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-size: 1.45rem;
  line-height: 1.25;
}
figcaption {
  margin-top: 16px;
  padding-top: 14px;
  font-size: 0.95rem;
  line-height: 1.45;
  border-top: 2px solid #111;
  width: fit-content;
}
.deco-a {
  top: 30px;
  right: -40px;
  width: 360px;
  height: 160px;
  transform: rotate(-8deg);
}
.deco-b {
  top: 170px;
  right: -60px;
  width: 460px;
  height: 120px;
  transform: rotate(-10deg);
}
@media (max-width: 1000px) {
  .reviews {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 560px) {
  .reviews li {
    grid-template-columns: minmax(0, 1fr);
  }
  .reviews img {
    min-height: 0;
    aspect-ratio: 16 / 10;
  }
}
</style>
