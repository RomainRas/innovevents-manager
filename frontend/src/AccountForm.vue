<script setup>
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { auth, refresh, request } from './auth'
const route = useRoute()
const mode = computed(() => route.meta.accountMode)
const titles = { register: 'Créer votre compte', forgot: 'Mot de passe oublié', password: 'Changer votre mot de passe' }
const fields = reactive({ email: '', prenom: '', nom: '', nom_utilisateur: '', current_password: '', password: '', confirmation: '' })
const busy = ref(false), error = ref(''), success = ref('')
const messages = {
  password_policy: 'Choisissez au moins 8 caractères avec une majuscule, une minuscule, un chiffre et un caractère spécial (72 octets maximum).',
  password_confirmation: 'Les deux mots de passe ne correspondent pas.',
  current_password_invalid: 'Le mot de passe actuel est incorrect.',
  password_must_change: 'Choisissez un mot de passe différent du précédent.',
  invalid_email: 'Vérifiez votre adresse électronique.', invalid_identity: 'Vérifiez les informations de votre identité.',
  too_many_attempts: 'Trop de tentatives. Réessayez dans quinze minutes.',
  authentication_required: 'Reconnectez-vous avant de changer votre mot de passe.'
}
async function submit() {
  busy.value = true; error.value = ''; success.value = ''
  try {
    if (!auth.csrf) await refresh()
    const data = await request(`/api/auth/${mode.value}`, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf }, body: JSON.stringify(fields) })
    if (mode.value === 'password') { auth.user = null; auth.csrf = data.csrf }
    success.value = mode.value === 'register' ? 'Si votre inscription peut être créée, un courriel de confirmation vous sera envoyé. Vous pouvez ensuite vous connecter.'
      : mode.value === 'forgot' ? 'Si un compte actif correspond à cette adresse, vous recevrez un mot de passe temporaire. Il est valable 30 minutes. Vérifiez aussi vos courriels indésirables.'
      : 'Votre mot de passe a été changé. Connectez-vous avec le nouveau mot de passe.'
  } catch (e) {
    error.value = messages[e.message] || 'La demande n’a pas pu aboutir. Réessayez dans un instant.'
    if (e.status === 403) { try { await refresh() } catch { /* Garder le message. */ } }
  } finally { fields.password = ''; fields.confirmation = ''; fields.current_password = ''; busy.value = false }
}
</script>
<template>
  <main class="panel narrow">
    <h1>{{ titles[mode] }}</h1>
    <p v-if="mode === 'password' && auth.user?.password_change_required">Vous devez choisir un nouveau mot de passe avant d’accéder à votre espace.</p>
    <p v-if="success" role="status">{{ success }}</p>
    <form v-else @submit.prevent="submit" :aria-busy="busy">
      <template v-if="mode !== 'password'">
        <label for="account-email">Adresse électronique</label><input id="account-email" v-model="fields.email" type="email" autocomplete="email" required maxlength="254" :disabled="busy">
      </template>
      <template v-if="mode === 'register'">
        <label for="first-name">Prénom</label><input id="first-name" v-model="fields.prenom" autocomplete="given-name" required maxlength="100" :disabled="busy">
        <label for="last-name">Nom</label><input id="last-name" v-model="fields.nom" autocomplete="family-name" required maxlength="100" :disabled="busy">
        <label for="username">Nom d’utilisateur</label><input id="username" v-model="fields.nom_utilisateur" autocomplete="nickname" required maxlength="100" :disabled="busy">
      </template>
      <template v-if="mode === 'password'">
        <label for="current-password">Mot de passe actuel ou temporaire</label><input id="current-password" v-model="fields.current_password" type="password" autocomplete="current-password" required :disabled="busy">
      </template>
      <template v-if="mode !== 'forgot'">
        <label for="new-password">{{ mode === 'register' ? 'Mot de passe' : 'Nouveau mot de passe' }}</label><input id="new-password" v-model="fields.password" type="password" autocomplete="new-password" required minlength="8" aria-describedby="password-help" :disabled="busy">
        <p id="password-help">Au moins 8 caractères : majuscule, minuscule, chiffre et caractère spécial. Maximum 72 octets.</p>
        <label for="confirmation">Confirmer le mot de passe</label><input id="confirmation" v-model="fields.confirmation" type="password" autocomplete="new-password" required :disabled="busy">
      </template>
      <p role="alert" class="error">{{ error }}</p>
      <button :disabled="busy">{{ busy ? 'Envoi…' : mode === 'register' ? 'Créer le compte' : mode === 'forgot' ? 'Recevoir un mot de passe temporaire' : 'Enregistrer le nouveau mot de passe' }}</button>
    </form>
    <p><RouterLink to="/connexion">Retour à la connexion</RouterLink></p>
  </main>
</template>
