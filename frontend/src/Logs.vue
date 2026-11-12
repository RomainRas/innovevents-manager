<script setup>
import { onMounted, ref } from 'vue'
import { request } from './auth'
const rows = ref([]), error = ref('')
onMounted(async () => {
  try { rows.value = (await request('/api/admin/logs')).logs }
  catch (e) { error.value = e.status === 403 ? 'Vous n’avez pas accès aux journaux.' : 'Les journaux sont indisponibles.' }
})
</script>
<template><main class="panel"><h1>Journaux d’actions</h1><p>Les vingt dernières actions enregistrées.</p>
  <p role="alert" class="error">{{ error }}</p>
  <ul><li v-for="(row, index) in rows" :key="index">{{ new Date(row.at).toLocaleString('fr-FR') }} — {{ row.type }}<br><small>Utilisateur : {{ row.actor || 'Non identifié' }}</small></li></ul>
</main></template>
