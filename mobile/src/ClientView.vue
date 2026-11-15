<script setup>
// Fiche contact mobile : appeler, envoyer un courriel ou lancer l'itinéraire vers le client.
import { computed, onMounted, ref } from 'vue'
import { api, state } from './api'

const props = defineProps({ id: { type: String, required: true }, from: { type: String, default: '' } })
const client = ref(null),
  loading = ref(true),
  error = ref('')

// Seuls les champs utiles aux trois actions sont renvoyés par l'API.
onMounted(async () => {
  try {
    client.value = (await api('/api/mobile/client?id=' + props.id)).client
  } catch (e) {
    if (e.status === 401) {
      state.user = null
      return
    }
    error.value =
      e.status === 404
        ? 'Ce client n’existe plus.'
        : e.status === 503 || e.status === 0
          ? 'Pas de connexion et cette fiche n’a pas encore été consultée sur ce téléphone.'
          : 'La fiche contact est indisponible pour le moment.'
  } finally {
    loading.value = false
  }
})

// Itinéraire : lien universel Google Maps, ouvert par l'application de cartographie du téléphone.
const route = computed(() =>
  client.value?.adresse
    ? 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(client.value.adresse)
    : null,
)
</script>
<template>
  <main class="page">
    <a class="back" :href="from ? '#/evenement/' + from : '#/'"
      >← {{ from ? 'Retour à l’événement' : 'Événements à venir' }}</a
    >
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <template v-if="client">
      <h1 tabindex="-1">{{ client.contact }}</h1>
      <p class="hello">{{ client.entreprise }}</p>
      <!-- Trois actions : appeler (tel:), écrire (mailto:), itinéraire (Google Maps) ; -->
      <!-- une action sans donnée est affichée comme indisponible -->
      <ul class="actions">
        <li v-if="client.telephone_composable">
          <a class="action" :href="'tel:' + client.telephone_composable"
            ><span class="icon" aria-hidden="true">✆</span
            ><span
              ><b>Appeler</b><span class="sub">{{ client.telephone }}</span></span
            ></a
          >
        </li>
        <li v-else class="action disabled">
          <span class="icon" aria-hidden="true">✆</span
          ><span><b>Téléphone</b><span class="sub">Numéro non renseigné</span></span>
        </li>
        <li v-if="client.email">
          <a class="action" :href="'mailto:' + client.email"
            ><span class="icon" aria-hidden="true">✉</span
            ><span
              ><b>Envoyer un courriel</b><span class="sub">{{ client.email }}</span></span
            ></a
          >
        </li>
        <li v-else class="action disabled">
          <span class="icon" aria-hidden="true">✉</span
          ><span><b>Courriel</b><span class="sub">Adresse non renseignée</span></span>
        </li>
        <li v-if="route">
          <a class="action" :href="route" target="_blank" rel="noopener noreferrer"
            ><span class="icon" aria-hidden="true">➤</span
            ><span
              ><b>Itinéraire</b><span class="sub">{{ client.adresse }}</span></span
            ></a
          >
        </li>
        <li v-else class="action disabled">
          <span class="icon" aria-hidden="true">➤</span
          ><span><b>Itinéraire</b><span class="sub">Adresse postale non renseignée</span></span>
        </li>
      </ul>
    </template>
  </main>
</template>
