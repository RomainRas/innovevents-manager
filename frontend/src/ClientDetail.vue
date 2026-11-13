<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { request } from './auth'
const route=useRoute(),client=ref(null),error=ref(''),loading=ref(true)
onMounted(async()=>{try{client.value=(await request('/api/admin/client?'+new URLSearchParams({id:route.params.id}))).client}catch(e){error.value=e.status===403?'Accès réservé à l’administratrice.':e.status===404?'Fiche client introuvable.':'Chargement impossible. Reconnectez-vous si nécessaire.'}finally{loading.value=false}})
</script>
<template><main class="panel"><RouterLink to="/prospects">Retour aux demandes</RouterLink><p v-if="loading" role="status">Chargement…</p><p v-if="error" role="alert" class="error">{{ error }}</p>
 <template v-if="client"><h1>{{ client.entreprise }}</h1><h2>Contact du client</h2><dl><dt>Nom</dt><dd>{{ client.contact_nom }}</dd><dt>Prénom</dt><dd>{{ client.contact_prenom }}</dd><dt>Adresse électronique</dt><dd>{{ client.email }}</dd><dt>Téléphone</dt><dd>{{ client.telephone }}</dd></dl></template>
 </main></template>
<style scoped>dt{font-weight:700;margin-top:16px}dd{margin:4px 0;overflow-wrap:anywhere}</style>
