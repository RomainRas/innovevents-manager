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
  <main class="work-page account-page">
    <!-- Décor : trois quarts de disque rouge, bleu et jaune en haut à droite -->
    <span class="disc red" aria-hidden="true"></span>
    <span class="disc blue" aria-hidden="true"></span>
    <span class="disc yellow" aria-hidden="true"></span>
    <h1 class="account-title">Bonjour {{ auth.user?.prenom }}</h1>
    <!-- Liens du compte, chacun précédé d'un pictogramme décoratif -->
    <p class="account-links">
      <RouterLink v-if="auth.user?.role === 'client'" to="/espace/informations"
        ><svg aria-hidden="true" viewBox="0 0 24 24">
          <circle cx="12" cy="8" r="4" />
          <path d="M4 21c0-4 4-6 8-6s8 2 8 6" /></svg
        >Modifier mes informations ou supprimer mon compte</RouterLink
      >
      <RouterLink to="/mot-de-passe"
        ><svg aria-hidden="true" viewBox="0 0 24 24">
          <rect x="5" y="11" width="14" height="10" rx="2" />
          <path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg
        >Changer mon mot de passe</RouterLink
      >
    </p>
    <!-- Raccourcis de l'équipe : événements, clients, modération des avis -->
    <nav v-if="auth.user?.role !== 'client'" aria-label="Espace équipe" class="team-links">
      <RouterLink to="/equipe/evenements">Tous les événements, notes et tâches</RouterLink>
      <RouterLink to="/equipe/clients">Tous les clients</RouterLink>
      <RouterLink to="/moderation-avis">Modérer les avis des clients</RouterLink>
    </nav>
    <!-- Tableau de bord, administratrice seulement -->
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
    <!-- Client : ses prochains événements ; équipe : dernières notes -->
    <div class="block-head">
      <span class="block-icon" aria-hidden="true"
        ><svg viewBox="0 0 24 24">
          <rect x="3" y="5" width="18" height="16" rx="2" />
          <path d="M3 10h18M8 3v4M16 3v4" /></svg
      ></span>
      <h2>{{ auth.user?.role === 'client' ? 'Vos prochains événements' : 'Notes de l’équipe' }}</h2>
    </div>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <ul v-if="!loading && !error && rows.length" class="row-list next-events">
      <li v-for="(row, i) in rows" :key="row.id">
        <svg v-if="row.debut" aria-hidden="true" viewBox="0 0 24 24" :class="['cal', ['red', 'blue', 'yellow'][i % 3]]">
          <rect x="3" y="5" width="18" height="16" rx="2" />
          <path d="M3 10h18M8 3v4M16 3v4" />
        </svg>
        <span class="row-name">{{ row.nom || row.contenu }}</span
        ><span v-if="row.debut" class="row-date"
          ><span class="sr-only"> — </span
          >{{ new Date(row.debut.replace(' ', 'T') + 'Z').toLocaleDateString('fr-FR') }}</span
        >
      </li>
    </ul>
    <p v-if="!loading && !error && !rows.length" class="empty-box">Aucune information à afficher pour le moment.</p>
    <!-- Client : ses devis et ses avis -->
    <ClientQuotes v-if="auth.user?.role === 'client'" />
    <ClientReviews v-if="auth.user?.role === 'client'" />
    <RouterLink v-if="auth.user?.role === 'administrateur'" to="/journaux">Consulter les journaux</RouterLink>
  </main>
</template>
<style scoped>
.account-page {
  position: relative;
  overflow: hidden;
}
.account-page > *:not(.disc) {
  position: relative;
}
.account-title {
  margin: 8px 0 12px;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-weight: 400;
  font-size: clamp(44px, 6vw, 68px);
  line-height: 1.05;
  color: #111;
}
.account-links {
  display: flex;
  flex-wrap: wrap;
  gap: 12px 28px;
  margin: 0 0 8px;
}
.account-links a {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  color: #111;
  font-size: 1.05rem;
}
.account-links a + a {
  padding-left: 28px;
  border-left: 1px solid #b9b09c;
}
.account-links svg,
.cal {
  flex: none;
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
}
.team-links {
  display: flex;
  flex-direction: column;
  gap: 12px;
  margin: 16px 0;
}
.next-events li {
  display: flex;
  align-items: center;
  gap: 16px;
}
.row-name {
  flex: 1;
  font-weight: 700;
  overflow-wrap: anywhere;
}
/* La date passe dans sa propre colonne ; le tiret reste pour la lecture vocale. */
.row-date {
  padding-left: 24px;
  border-left: 1px solid #d8cfbd;
  white-space: nowrap;
}
.cal.red {
  color: #c8221a;
}
.cal.blue {
  color: #1d3fbf;
}
.cal.yellow {
  color: #d4a600;
}
.disc {
  position: absolute;
  border-radius: 50%;
  pointer-events: none;
}
.disc.red {
  top: -160px;
  right: 80px;
  width: 300px;
  height: 300px;
  background: #c8221a;
}
.disc.blue {
  top: -40px;
  right: -120px;
  width: 300px;
  height: 300px;
  background: #1d3fbf;
}
.disc.yellow {
  top: 90px;
  right: -170px;
  width: 300px;
  height: 300px;
  background: #ffd836;
}
@media (max-width: 900px) {
  .disc {
    display: none;
  }
}
@media (max-width: 600px) {
  .account-links a + a {
    padding-left: 0;
    border-left: 0;
  }
}
</style>
