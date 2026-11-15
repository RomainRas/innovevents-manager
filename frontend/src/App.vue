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
  <header>
    <RouterLink class="brand" to="/">Innov’Events</RouterLink>
    <nav aria-label="Navigation principale">
      <RouterLink to="/">Accueil</RouterLink>
      <RouterLink to="/evenements-publics">Évènements</RouterLink>
      <RouterLink to="/avis">Avis</RouterLink>
      <RouterLink to="/contact">Contact</RouterLink>
      <RouterLink to="/demande-devis">Demande de devis</RouterLink>
      <template v-if="auth.user"
        ><RouterLink to="/espace">Mon espace</RouterLink><button @click="disconnect">Se déconnecter</button></template
      >
      <RouterLink v-else to="/connexion">Se connecter</RouterLink>
    </nav>
  </header>
  <p v-if="error" role="alert" class="error">{{ error }}</p>
  <div id="contenu" tabindex="-1"><RouterView :key="$route.path" /></div>
  <footer>
    <nav aria-label="Informations légales">
      <RouterLink to="/mentions-legales">Mentions légales</RouterLink>
      <RouterLink to="/cgu">Conditions générales d’utilisation</RouterLink>
      <RouterLink to="/cgv">Conditions générales de vente</RouterLink>
      <RouterLink to="/confidentialite">Confidentialité</RouterLink>
      <RouterLink to="/contact">Contact</RouterLink>
    </nav>
    <p>© 2026 Innov’Events — entreprise fictive, projet de formation.</p>
  </footer>
</template>
