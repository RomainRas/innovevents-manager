<script setup>
// Page publique des avis clients : seuls les avis validés par l'équipe sont renvoyés par l'API.
import { onMounted, ref } from 'vue'
import { request } from './auth'

const reviews = ref([]),
  error = ref(''),
  loading = ref(true)
const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeZone: 'UTC' })

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
  <main class="panel wide">
    <h1>Avis de nos clients</h1>
    <p>
      Les avis sont déposés par nos clients depuis leur espace après leur événement, puis vérifiés par notre équipe
      avant publication.
    </p>
    <p v-if="loading">Chargement des avis…</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>
    <p v-else-if="!reviews.length">Aucun avis publié pour le moment.</p>
    <ul v-else class="reviews">
      <li v-for="r in reviews" :key="r.id">
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
  </main>
</template>
