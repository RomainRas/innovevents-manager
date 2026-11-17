<script setup>
// Espace personnel, adapté au rôle : le client voit ses événements, ses devis et ses avis ;
// l'équipe voit les dernières notes, et l'administratrice a en plus son tableau de bord.
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, request, refresh } from './auth'
import ClientQuotes from './ClientQuotes.vue'
import ClientReviews from './ClientReviews.vue'
import AdminDashboard from './AdminDashboard.vue'
import GlobalNotes from './GlobalNotes.vue'
import Icon from './Icon.vue'

const rows = ref([]),
  error = ref(''),
  loading = ref(true)
const router = useRouter()
const isClient = computed(() => auth.user?.role === 'client'),
  isAdmin = computed(() => auth.user?.role === 'administrateur')

// Accès rapides de l'équipe et raccourcis de l'administratrice : lien, libellé, pictogramme, couleur.
const quick = [
  ['/mot-de-passe', 'Changer mon mot de passe', 'lock', 'red'],
  ['/equipe/evenements', 'Tous les événements, notes et tâches', 'calendar', 'blue'],
  ['/equipe/clients', 'Tous les clients', 'users', 'yellow'],
  ['/moderation-avis', 'Modérer les avis des clients', 'star', 'blue'],
]
const shortcuts = [
  ['/comptes', 'Gérer les comptes employés et clients', 'users', 'red'],
  ['/prospects', 'Consulter les demandes reçues', 'mail', 'blue'],
  ['/devis', 'Suivre les devis et les réponses des clients', 'file', 'yellow'],
  ['/parametres-demandes', 'Modifier le remerciement des demandes', 'pen', 'red'],
]

// Événements du client ; une session expirée renvoie vers la connexion. Les notes de l'équipe se chargent dans GlobalNotes.
onMounted(async () => {
  if (!isClient.value) {
    loading.value = false
    return
  }
  try {
    rows.value = (await request('/api/me/events')).events
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
    <template v-if="isClient">
      <span class="disc red" aria-hidden="true"></span>
      <span class="disc blue" aria-hidden="true"></span>
      <span class="disc yellow" aria-hidden="true"></span>
    </template>
    <!-- Décor de l'équipe : trois traits inclinés rouge, bleu et jaune -->
    <span v-else class="stripes" aria-hidden="true"><i></i><i></i><i></i></span>
    <h1 class="account-title" :class="{ slash: !isClient }">Bonjour {{ auth.user?.prenom }}</h1>
    <p v-if="!isClient" class="welcome">
      {{
        isAdmin
          ? 'Bienvenue dans l’espace d’administration d’Innov’Events.'
          : 'Bienvenue dans l’espace de l’équipe Innov’Events.'
      }}
    </p>
    <!-- Client : liens du compte, chacun précédé d'un pictogramme décoratif -->
    <p v-if="isClient" class="account-links">
      <RouterLink to="/espace/informations"
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
    <!-- Équipe : accès rapides en cartes (mot de passe, événements, clients, modération) -->
    <nav v-else aria-label="Espace équipe" class="cards quick">
      <RouterLink v-for="[to, label, icon, color] in quick" :key="to" :to="to" class="card" :class="color"
        ><span class="card-icon"><Icon :name="icon" /></span><span class="card-label">{{ label }}</span
        ><Icon name="arrow" class="card-arrow"
      /></RouterLink>
    </nav>
    <!-- Tableau de bord, administratrice seulement -->
    <AdminDashboard v-if="isAdmin" />
    <!-- Raccourcis opérationnels de l'administratrice -->
    <template v-if="isAdmin">
      <h2 class="slash-title yellow">Raccourcis opérationnels</h2>
      <nav aria-label="Raccourcis opérationnels" class="cards shortcuts">
        <RouterLink v-for="[to, label, icon, color] in shortcuts" :key="to" :to="to" class="card" :class="color"
          ><span class="card-icon"><Icon :name="icon" /></span><span class="card-label">{{ label }}</span
          ><Icon name="arrow" class="card-arrow"
        /></RouterLink>
      </nav>
    </template>
    <!-- Client : ses prochains événements, ses devis et ses avis -->
    <template v-if="isClient">
      <div class="block-head">
        <span class="block-icon" aria-hidden="true"
          ><svg viewBox="0 0 24 24">
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M3 10h18M8 3v4M16 3v4" /></svg
        ></span>
        <h2>Vos prochains événements</h2>
      </div>
      <p v-if="loading" role="status">Chargement…</p>
      <p v-if="error" role="alert" class="error">{{ error }}</p>
      <ul v-if="!loading && !error && rows.length" class="row-list next-events">
        <li v-for="(row, i) in rows" :key="row.id">
          <svg aria-hidden="true" viewBox="0 0 24 24" :class="['cal', ['red', 'blue', 'yellow'][i % 3]]">
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M3 10h18M8 3v4M16 3v4" />
          </svg>
          <span class="row-name">{{ row.nom }}</span
          ><span v-if="row.debut" class="row-date"
            ><span class="sr-only"> — </span
            >{{ new Date(row.debut.replace(' ', 'T') + 'Z').toLocaleDateString('fr-FR') }}</span
          >
        </li>
      </ul>
      <p v-if="!loading && !error && !rows.length" class="empty-box">Aucune information à afficher pour le moment.</p>
      <ClientQuotes />
      <ClientReviews />
    </template>
    <!-- Équipe : notes de l'équipe sur fond sombre ; journaux pour l'administratrice -->
    <template v-else>
      <h2 class="slash-title">Notes de l’équipe</h2>
      <div class="team-notes" :class="{ solo: !isAdmin }">
        <GlobalNotes :admin="isAdmin" />
        <RouterLink v-if="isAdmin" to="/journaux" class="card blue logs"
          ><span class="card-icon"><Icon name="server" /></span><span class="card-label">Consulter les journaux</span
          ><Icon name="arrow" class="card-arrow"
        /></RouterLink>
      </div>
    </template>
  </main>
</template>
<style scoped>
.account-page {
  position: relative;
  overflow: hidden;
}
.account-page > *:not(.disc):not(.stripes) {
  position: relative;
}
.account-title {
  margin: 8px 0 12px;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: clamp(44px, 6vw, 68px);
  line-height: 1.05;
  color: var(--encre);
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
  color: var(--encre);
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
/* Équipe : titre en majuscules précédé d'un trait rouge incliné. */
.account-title.slash {
  display: flex;
  align-items: center;
  gap: 20px;
  text-transform: uppercase;
}
.account-title.slash::before {
  content: '';
  flex: none;
  width: 13px;
  height: 0.8em;
  background: var(--accent, var(--rouge));
  transform: skew(-18deg);
}
.welcome {
  margin: -4px 0 28px 34px;
  font-size: 1.15rem;
}
/* Cartes de liens : pictogramme, libellé, flèche ; liseré coloré en bas pour les raccourcis. */
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}
.card {
  display: flex;
  align-items: center;
  gap: 16px;
  min-height: 72px;
  padding: 14px 18px;
  color: var(--encre);
  font-weight: 600;
  text-decoration: none;
  background: var(--creme);
  border: 1px solid var(--filet);
  border-radius: 8px;
}
.card:hover {
  border-color: var(--encre);
}
.card-label {
  flex: 1;
}
.card-icon .icon {
  width: 30px;
  height: 30px;
}
.shortcuts .card,
.card.logs {
  border-bottom: 4px solid var(--accent, var(--rouge));
}
.card.blue {
  --accent: var(--bleu);
}
.card.yellow {
  --accent: var(--jaune);
}
.team-notes.solo {
  grid-template-columns: minmax(0, 1fr);
}
.team-notes {
  display: grid;
  grid-template-columns: minmax(0, 3fr) minmax(220px, 1fr);
  gap: 20px;
  align-items: start;
}
@media (max-width: 800px) {
  .team-notes {
    grid-template-columns: minmax(0, 1fr);
  }
  .welcome {
    margin-left: 0;
  }
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
  border-left: 1px solid var(--filet);
  white-space: nowrap;
}
.cal.red {
  color: var(--rouge);
}
.cal.blue {
  color: var(--bleu);
}
.cal.yellow {
  color: #d4a600;
}
.stripes {
  position: absolute;
  top: 24px;
  right: max(16px, calc((100% - 1100px) / 2));
  display: flex;
  gap: 10px;
}
.stripes i {
  width: 26px;
  height: 64px;
  background: var(--rouge);
  transform: skew(-22deg);
}
.stripes i:nth-child(2) {
  background: var(--bleu);
}
.stripes i:nth-child(3) {
  background: var(--jaune);
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
  background: var(--rouge);
}
.disc.blue {
  top: -40px;
  right: -120px;
  width: 300px;
  height: 300px;
  background: var(--bleu);
}
.disc.yellow {
  top: 90px;
  right: -170px;
  width: 300px;
  height: 300px;
  background: var(--jaune);
}
@media (max-width: 900px) {
  .disc,
  .stripes {
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
