<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { auth, logout } from './auth'
const router = useRouter(), error = ref('')
async function disconnect() {
  try { await logout(); error.value = ''; await router.push('/connexion') }
  catch { error.value = 'Déconnexion impossible. Réessayez.' }
}
</script>
<template>
  <header><RouterLink class="brand" to="/">Innov’Events</RouterLink>
    <nav aria-label="Navigation principale"><RouterLink to="/">Accueil</RouterLink>
      <template v-if="auth.user"><RouterLink to="/espace">Mon espace</RouterLink><button @click="disconnect">Se déconnecter</button></template>
      <RouterLink v-else to="/connexion">Connexion</RouterLink>
    </nav>
  </header>
  <p v-if="error" role="alert" class="error">{{ error }}</p><RouterView :key="$route.path" />
</template>
