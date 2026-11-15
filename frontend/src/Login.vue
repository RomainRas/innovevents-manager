<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { login, refresh } from './auth'
const email = ref(''), password = ref(''), error = ref(''), busy = ref(false)
const route = useRoute(), router = useRouter()
const destinations = ['/espace', '/journaux', '/prospects', '/parametres-demandes', '/mot-de-passe', '/devis', '/espace/informations', '/moderation-avis']
async function submit() {
  busy.value = true; error.value = ''
  try {
    await login(email.value, password.value)
    const retour = route.query.retour
    const target = typeof retour === 'string' && (destinations.includes(retour) || /^\/prospects\/[1-9][0-9]{0,17}(\/(convertir|evenement))?$/.test(retour) || /^\/(clients|evenements)\/[1-9][0-9]{0,17}(\/devis)?$/.test(retour)) ? retour : '/espace'
    await router.replace(target)
  } catch (e) {
    error.value = e.status === 429 ? 'Trop de tentatives. Réessayez dans quinze minutes.'
      : e.status === 401 ? 'Adresse électronique ou mot de passe incorrect.'
      : e.status === 403 ? 'La session a expiré. Réessayez.'
      : 'Connexion indisponible. Réessayez dans un instant.'
    try { await refresh() } catch { /* Conserver le message accessible. */ }
  } finally { password.value = ''; busy.value = false }
}
</script>
<template>
  <main class="panel narrow">
    <h1>Connexion</h1><p>Retrouvez votre espace Innov’Events.</p>
    <form @submit.prevent="submit" :aria-busy="busy">
      <label for="email">Adresse électronique</label>
      <input id="email" v-model="email" type="email" autocomplete="username" required maxlength="254" :disabled="busy">
      <label for="password">Mot de passe</label>
      <input id="password" v-model="password" type="password" autocomplete="current-password" required :disabled="busy" aria-describedby="login-error">
      <p id="login-error" role="alert" class="error">{{ error }}</p>
      <button :disabled="busy">{{ busy ? 'Connexion…' : 'Se connecter' }}</button>
    </form>
    <p><RouterLink to="/mot-de-passe-oublie">Mot de passe oublié ?</RouterLink></p>
    <p><RouterLink to="/inscription">Créer un compte</RouterLink></p>
  </main>
</template>
