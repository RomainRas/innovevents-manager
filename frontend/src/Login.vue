<script setup>
// Écran de connexion. Après succès, retour vers la page demandée avant la connexion, si elle est autorisée.
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { login, refresh } from './auth'

const email = ref(''),
  password = ref(''),
  error = ref(''),
  busy = ref(false)
const route = useRoute(),
  router = useRouter()

// Pages de retour acceptées (liste fermée) : un lien piégé ne peut pas rediriger vers un autre site.
const destinations = [
  '/espace',
  '/journaux',
  '/prospects',
  '/parametres-demandes',
  '/mot-de-passe',
  '/devis',
  '/espace/informations',
  '/moderation-avis',
  '/comptes',
]

async function submit() {
  busy.value = true
  error.value = ''
  try {
    await login(email.value, password.value)
    const retour = route.query.retour
    // Retour demandé accepté seulement s'il est dans la liste ou s'il correspond à une fiche (identifiant numérique).
    const target =
      typeof retour === 'string' &&
      (destinations.includes(retour) ||
        /^\/prospects\/[1-9][0-9]{0,17}(\/(convertir|evenement))?$/.test(retour) ||
        /^\/(clients|evenements)\/[1-9][0-9]{0,17}(\/devis)?$/.test(retour) ||
        /^\/equipe\/(clients|evenements)(\/[1-9][0-9]{0,17})?$/.test(retour))
        ? retour
        : '/espace'
    await router.replace(target)
  } catch (e) {
    // Mot de passe effacé immédiatement, sans attendre le rafraîchissement de la session.
    password.value = ''
    error.value =
      e.status === 429
        ? 'Trop de tentatives. Réessayez dans quinze minutes.'
        : e.status === 401
          ? 'Adresse électronique ou mot de passe incorrect.'
          : e.status === 403
            ? 'La session a expiré. Réessayez.'
            : 'Connexion indisponible. Réessayez dans un instant.'
    try {
      await refresh()
    } catch {
      /* Conserver le message accessible. */
    }
  } finally {
    password.value = ''
    busy.value = false
  }
}
</script>
<template>
  <main class="poster-page login-page">
    <!-- Décor : aplats déchirés et ticket « Événements » -->
    <span class="shape red torn-a deco-a" aria-hidden="true"></span>
    <span class="shape yellow torn-b deco-b" aria-hidden="true"></span>
    <span class="shape blue torn-a deco-c" aria-hidden="true"></span>
    <span class="shape yellow torn-b deco-d" aria-hidden="true"></span>
    <span class="ticket-stamp" aria-hidden="true">★ Événements ★</span>
    <div class="paper login-card">
      <h1 class="poster-title yellow">Connexion</h1>
      <p class="poster-lead">Retrouvez votre espace Innov’Events.</p>
      <!-- Identifiants ; liens vers l'inscription et le mot de passe oublié -->
      <form @submit.prevent="submit" :aria-busy="busy">
        <label for="email">Adresse électronique</label>
        <input
          id="email"
          v-model="email"
          type="email"
          autocomplete="username"
          required
          maxlength="254"
          :disabled="busy"
        />
        <label for="password">Mot de passe</label>
        <input
          id="password"
          v-model="password"
          type="password"
          autocomplete="current-password"
          required
          :disabled="busy"
          aria-describedby="login-error"
        />
        <p id="login-error" role="alert" class="error">{{ error }}</p>
        <button class="poster-button" :disabled="busy">{{ busy ? 'Connexion…' : 'Se connecter' }}</button>
      </form>
      <p class="login-links">
        <RouterLink to="/mot-de-passe-oublie">Mot de passe oublié ?</RouterLink>
        <RouterLink to="/inscription">Créer un compte</RouterLink>
      </p>
    </div>
  </main>
</template>
<style scoped>
/* Connexion : carte de papier centrée sur fond d'aplats. */
.login-card {
  max-width: 640px;
  margin: 0 auto;
  text-align: center;
}
.login-card form {
  text-align: left;
}
.login-card .poster-lead {
  margin-inline: auto;
}
.login-card .poster-button {
  display: block;
  margin: 8px auto 0;
}
.ticket-stamp {
  top: 96px;
  left: calc(50% + 230px);
  z-index: 2;
  font-size: 1.3rem;
}
.login-links {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 12px 32px;
  margin: 24px 0 0;
}
.deco-a {
  top: -20px;
  left: -80px;
  width: 520px;
  height: 420px;
  transform: rotate(-8deg);
}
.deco-b {
  bottom: 60px;
  left: -60px;
  width: 380px;
  height: 120px;
  transform: rotate(10deg);
}
.deco-c {
  right: -60px;
  bottom: 40px;
  width: 480px;
  height: 360px;
  transform: rotate(-14deg);
}
.deco-d {
  right: -40px;
  bottom: 360px;
  width: 380px;
  height: 90px;
  transform: rotate(-24deg);
}
@media (max-width: 700px) {
  .ticket-stamp {
    display: none;
  }
}
</style>
