<script setup>
// Cadre commun à toutes les pages : en-tête, navigation selon la connexion, pied de page avec les pages légales.
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, logout } from './auth'

const router = useRouter(),
  error = ref('')

// Déconnexion depuis l'en-tête, puis retour à l'écran de connexion.
async function disconnect() {
  try {
    await logout()
    error.value = ''
    await router.push('/connexion')
  } catch {
    error.value = 'Déconnexion impossible. Réessayez.'
  }
}
</script>
<template>
  <a class="skip-link" href="#contenu">Aller au contenu</a>
  <!-- En-tête : logo et navigation, différente selon la connexion -->
  <header>
    <RouterLink class="brand" to="/">Innov’Events</RouterLink>
    <nav aria-label="Navigation principale">
      <RouterLink to="/">Accueil</RouterLink>
      <RouterLink to="/evenements-publics">Événements</RouterLink>
      <RouterLink to="/avis">Avis</RouterLink>
      <RouterLink to="/contact">Contact</RouterLink>
      <RouterLink class="nav-quote" to="/demande-devis">Demande de devis <span aria-hidden="true">→</span></RouterLink>
      <template v-if="auth.user"
        ><RouterLink class="nav-account" to="/espace">Mon espace</RouterLink
        ><button class="nav-account" @click="disconnect">Se déconnecter</button></template
      >
      <!-- Connexion : pictogramme décoratif, le libellé reste le nom du lien -->
      <RouterLink v-else class="nav-account" to="/connexion"
        ><svg aria-hidden="true" viewBox="0 0 24 24" width="18" height="18">
          <circle cx="12" cy="8" r="4" fill="none" stroke="currentColor" stroke-width="2" />
          <path d="M4 21c0-4 4-6 8-6s8 2 8 6" fill="none" stroke="currentColor" stroke-width="2" /></svg
        >Se connecter</RouterLink
      >
    </nav>
  </header>
  <p v-if="error" role="alert" class="error">{{ error }}</p>
  <!-- Page courante ; la clé recrée l'écran à chaque changement d'adresse -->
  <div id="contenu" tabindex="-1"><RouterView :key="$route.path" /></div>
  <footer>
    <!-- Pied de page : logo, plan du site et pages légales sur fond noir au bord déchiré -->
    <div class="footer-inner">
      <RouterLink class="footer-logo" to="/">Innov’Events</RouterLink>
      <nav aria-label="Plan du site">
        <RouterLink to="/">Accueil</RouterLink>
        <RouterLink to="/evenements-publics">Événements</RouterLink>
        <RouterLink to="/avis">Avis</RouterLink>
        <RouterLink to="/contact">Contact</RouterLink>
      </nav>
      <nav aria-label="Informations légales">
        <RouterLink to="/mentions-legales">Mentions légales</RouterLink>
        <RouterLink to="/cgu">Conditions générales d’utilisation</RouterLink>
        <RouterLink to="/cgv">Conditions générales de vente</RouterLink>
        <RouterLink to="/confidentialite">Confidentialité</RouterLink>
      </nav>
    </div>
    <p>© 2026 Innov’Events — entreprise fictive, projet de formation.</p>
  </footer>
</template>
