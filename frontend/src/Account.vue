<script setup>
// Espace personnel, adapté au rôle : le client voit ses événements, ses devis et ses avis ;
// l'équipe voit les dernières notes, et l'administratrice a en plus son tableau de bord.
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, request, refresh } from './auth'
import ClientQuotes from './ClientQuotes.vue'
import ClientReviews from './ClientReviews.vue'
import AdminDashboard from './AdminDashboard.vue'

const rows = ref([]),
  error = ref(''),
  loading = ref(true)
const router = useRouter()

// Données du tableau de bord selon le rôle ; une session expirée renvoie vers la connexion.
onMounted(async () => {
  try {
    const data = await request(auth.user?.role === 'client' ? '/api/me/events' : '/api/team/notes')
    rows.value = data.events || data.notes
  } catch (e) {
    if (e.status === 401) {
      await refresh()
      await router.replace('/connexion?retour=/espace')
    } else
      error.value =
        e.message === 'password_change_required'
          ? 'Un changement de mot de passe est requis avant de poursuivre.'
          : 'Cet espace est indisponible pour le moment.'
  } finally {
    loading.value = false
  }
})
</script>
<template>
  <main class="panel">
    <h1>Bonjour {{ auth.user?.prenom }}</h1>
    <p v-if="auth.user?.role === 'client'">
      <RouterLink to="/espace/informations">Modifier mes informations ou supprimer mon compte</RouterLink>
    </p>
    <p><RouterLink to="/mot-de-passe">Changer mon mot de passe</RouterLink></p>
    <nav v-if="auth.user?.role !== 'client'" aria-label="Espace équipe" class="team-links">
      <RouterLink to="/equipe/evenements">Tous les événements, notes et tâches</RouterLink>
      <RouterLink to="/equipe/clients">Tous les clients</RouterLink>
      <RouterLink to="/moderation-avis">Modérer les avis des clients</RouterLink>
    </nav>
    <AdminDashboard v-if="auth.user?.role === 'administrateur'" />
    <p v-if="auth.user?.role === 'administrateur'">
      <RouterLink to="/comptes">Gérer les comptes employés et clients</RouterLink>
    </p>
    <p v-if="auth.user?.role === 'administrateur'">
      <RouterLink to="/prospects">Consulter les demandes reçues</RouterLink>
    </p>
    <p v-if="auth.user?.role === 'administrateur'">
      <RouterLink to="/devis">Suivre les devis et les réponses des clients</RouterLink>
    </p>
    <p v-if="auth.user?.role === 'administrateur'">
      <RouterLink to="/parametres-demandes">Modifier le remerciement des demandes</RouterLink>
    </p>
    <h2>{{ auth.user?.role === 'client' ? 'Vos prochains événements' : 'Notes de l’équipe' }}</h2>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <ul v-if="!loading && !error">
      <li v-for="row in rows" :key="row.id">
        {{ row.nom || row.contenu
        }}<span v-if="row.debut"> — {{ new Date(row.debut.replace(' ', 'T') + 'Z').toLocaleDateString('fr-FR') }}</span>
      </li>
    </ul>
    <p v-if="!loading && !error && !rows.length">Aucune information à afficher pour le moment.</p>
    <ClientQuotes v-if="auth.user?.role === 'client'" />
    <ClientReviews v-if="auth.user?.role === 'client'" />
    <RouterLink v-if="auth.user?.role === 'administrateur'" to="/journaux">Consulter les journaux</RouterLink>
  </main>
</template>
<style scoped>
.team-links {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 16px 0;
}
</style>
