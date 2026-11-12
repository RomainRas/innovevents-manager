<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, request, refresh } from './auth'
const rows = ref([]), error = ref(''), loading = ref(true)
const router = useRouter()
onMounted(async () => {
  try {
    const data = await request(auth.user?.role === 'client' ? '/api/me/events' : '/api/team/notes')
    rows.value = data.events || data.notes
  } catch (e) {
    if (e.status === 401) { await refresh(); await router.replace('/connexion?retour=/espace') }
    else error.value = e.message === 'password_change_required' ? 'Un changement de mot de passe est requis avant de poursuivre.' : 'Cet espace est indisponible pour le moment.'
  } finally { loading.value = false }
})
</script>
<template>
  <main class="panel">
    <h1>Bonjour {{ auth.user?.prenom }}</h1>
    <p><RouterLink to="/mot-de-passe">Changer mon mot de passe</RouterLink></p>
    <p v-if="auth.user?.role === 'administrateur'"><RouterLink to="/parametres-demandes">Modifier le remerciement des demandes</RouterLink></p>
    <h2>{{ auth.user?.role === 'client' ? 'Vos prochains événements' : 'Notes de l’équipe' }}</h2>
    <p v-if="loading" role="status">Chargement…</p><p v-if="error" role="alert" class="error">{{ error }}</p>
    <ul v-if="!loading && !error"><li v-for="row in rows" :key="row.id">{{ row.nom || row.contenu }}<span v-if="row.debut"> — {{ new Date(row.debut.replace(' ', 'T') + 'Z').toLocaleDateString('fr-FR') }}</span></li></ul>
    <p v-if="!loading && !error && !rows.length">Aucune information à afficher pour le moment.</p>
    <RouterLink v-if="auth.user?.role === 'administrateur'" to="/journaux">Consulter les journaux</RouterLink>
  </main>
</template>
