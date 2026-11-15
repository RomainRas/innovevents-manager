<script setup>
// Écran de connexion de l'application mobile.
import { nextTick, ref } from 'vue'
import { login, session, state } from './api'

const email = ref(''),
  password = ref(''),
  error = ref(''),
  busy = ref(false)

/** Connexion ; un mot de passe à changer renvoie vers le site, où ce changement se fait. */
async function submit() {
  error.value = ''
  if (!email.value || !password.value) {
    error.value = 'Saisissez votre adresse électronique et votre mot de passe.'
    await nextTick()
    document.getElementById('login-error')?.focus()
    return
  }
  busy.value = true
  try {
    await login(email.value.trim(), password.value)
    if (state.user?.password_change_required)
      error.value = 'Choisissez d’abord un nouveau mot de passe sur le site, puis revenez dans l’application.'
  } catch (e) {
    error.value =
      e.status === 401
        ? 'Adresse électronique ou mot de passe incorrect.'
        : e.status === 429
          ? 'Trop de tentatives. Réessayez dans quinze minutes.'
          : e.status === 0
            ? 'Pas de connexion réseau : la connexion nécessite Internet.'
            : e.status === 403
              ? 'La session a expiré. Réessayez.'
              : 'Connexion indisponible. Réessayez dans un instant.'
    try {
      await session()
    } catch {
      /* Message conservé. */
    }
  }
  busy.value = false
  if (error.value) {
    await nextTick()
    document.getElementById('login-error')?.focus()
  }
}
</script>
<template>
  <main class="page">
    <h1 tabindex="-1">Connexion</h1>
    <p>Application réservée à l’équipe : événements à venir, contacts clients et notes rapides.</p>
    <p v-if="error" id="login-error" class="error" role="alert" tabindex="-1">{{ error }}</p>
    <form novalidate @submit.prevent="submit">
      <label for="login-email">Adresse électronique</label>
      <input id="login-email" v-model="email" type="email" autocomplete="username" inputmode="email" required />
      <label for="login-password">Mot de passe</label>
      <input id="login-password" v-model="password" type="password" autocomplete="current-password" required />
      <button type="submit" class="primary" :disabled="busy">{{ busy ? 'Connexion…' : 'Se connecter' }}</button>
    </form>
    <p class="legal">
      <a href="/cgu">Conditions générales d’utilisation</a> · <a href="/cgv">Conditions générales de vente</a> ·
      <a href="/confidentialite">Confidentialité</a>
    </p>
  </main>
</template>
