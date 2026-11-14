<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { request } from './auth'
import { period, statusLabels } from './publicFormat'
const route = useRoute(), event = ref(null), error = ref(''), loading = ref(true)
onMounted(async () => {
  try {
    event.value = (await request('/api/public/event?id=' + encodeURIComponent(route.params.id))).event
    document.title = event.value.nom + ' — Innov’Events'
  } catch (e) { error.value = e.status === 404 ? 'Cet événement n’existe pas ou n’est pas publié.' : 'L’événement est momentanément indisponible.' }
  finally { loading.value = false }
})
</script>
<template>
  <main class="panel wide">
    <p><RouterLink to="/evenements-publics">← Tous les événements</RouterLink></p>
    <p v-if="loading">Chargement…</p>
    <template v-else-if="event">
      <article class="event-detail">
        <h1>{{ event.nom }}</h1>
        <img v-if="event.image" :src="`/api/public/event-image?id=${event.id}`" :alt="event.image_alternative || ''" width="1200" height="750">
        <dl>
          <dt>Dates</dt><dd>{{ period(event.debut, event.fin) }}</dd>
          <dt>Lieu</dt><dd>{{ event.lieu }}</dd>
          <dt>Type d’événement</dt><dd>{{ event.type_evenement }}</dd>
          <template v-if="event.theme"><dt>Thème</dt><dd>{{ event.theme }}</dd></template>
          <template v-if="event.participants"><dt>Participants</dt><dd>{{ event.participants }}</dd></template>
          <dt>Statut</dt><dd>{{ statusLabels[event.statut] }}</dd>
        </dl>
      </article>
      <section class="quote-callout" aria-labelledby="quote-title">
        <h2 id="quote-title">Prestations et Devis</h2>
        <p>Vous imaginez un événement similaire ? Décrivez votre projet et recevez un devis personnalisé.</p>
        <RouterLink class="button" to="/demande-devis">Demander un devis</RouterLink>
      </section>
    </template>
    <p v-else class="error" role="alert">{{ error }}</p>
  </main>
</template>
