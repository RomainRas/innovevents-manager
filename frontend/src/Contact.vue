<script setup>
// Formulaire de contact public : le message est envoyé par courriel à l'agence (nombre d'envois limité).
import { nextTick, reactive, ref } from 'vue'
import { auth, refresh, request } from './auth'

// Adresse pré-remplie pour un utilisateur connecté.
const form = reactive({ nom_utilisateur: '', email: auth.user?.email || '', titre: '', description: '' })
const busy = ref(false),
  sent = ref(false),
  error = ref(''),
  errors = ref({})

async function submit() {
  busy.value = true
  error.value = ''
  errors.value = {}
  try {
    if (!auth.csrf) await refresh()
    await request('/api/contact', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify(form),
    })
    sent.value = true
    await nextTick()
    document.getElementById('contact-success')?.focus()
  } catch (e) {
    errors.value = e.fields || {}
    if (e.status === 422) error.value = 'Vérifiez les champs signalés.'
    else if (e.status === 429) error.value = 'Trop de messages envoyés. Réessayez dans quinze minutes.'
    else if (e.status === 403) {
      error.value = 'La session a expiré. Envoyez à nouveau votre message.'
      try {
        await refresh()
      } catch {
        /* Saisie conservée. */
      }
    } else
      error.value = 'L’envoi n’a pas pu être confirmé. Votre message est conservé : réessayez dans quelques instants.'
    // Réactiver les champs avant d'y placer le focus (un champ désactivé ne peut pas le recevoir).
    busy.value = false
    await nextTick()
    document
      .getElementById(Object.keys(errors.value)[0] ? 'contact-' + Object.keys(errors.value)[0] : 'contact-errors')
      ?.focus()
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <main class="panel narrow-form">
    <h1>Contact</h1>
    <section v-if="sent" id="contact-success" tabindex="-1" role="status">
      <h2>Message envoyé</h2>
      <p>
        Merci, votre message a été transmis à l’équipe Innov’Events. Nous vous répondrons dans les meilleurs délais.
      </p>
      <RouterLink to="/">Retour à l’accueil</RouterLink>
    </section>
    <form v-else @submit.prevent="submit" novalidate :aria-busy="busy">
      <p>
        Un problème, une question ? Écrivez-nous. Les champs marqués * sont obligatoires. Indiquez votre adresse
        électronique, ou à défaut votre nom d’utilisateur si vous avez un compte.
      </p>
      <div v-if="error" id="contact-errors" class="error" role="alert" tabindex="-1">{{ error }}</div>
      <fieldset :disabled="busy">
        <label for="contact-nom_utilisateur">Nom d’utilisateur (facultatif)</label>
        <input
          id="contact-nom_utilisateur"
          v-model="form.nom_utilisateur"
          maxlength="100"
          autocomplete="username"
          :aria-invalid="!!errors.nom_utilisateur"
          :aria-describedby="errors.nom_utilisateur ? 'err-nom' : undefined"
        />
        <p v-if="errors.nom_utilisateur" id="err-nom" class="error">{{ errors.nom_utilisateur }}</p>
        <label for="contact-email">Adresse électronique (obligatoire sans nom d’utilisateur)</label>
        <input
          id="contact-email"
          v-model="form.email"
          type="email"
          maxlength="254"
          autocomplete="email"
          :aria-invalid="!!errors.email"
          :aria-describedby="errors.email ? 'err-email' : undefined"
        />
        <p v-if="errors.email" id="err-email" class="error">{{ errors.email }}</p>
        <label for="contact-titre">Titre de la demande *</label>
        <input
          id="contact-titre"
          v-model="form.titre"
          maxlength="150"
          required
          :aria-invalid="!!errors.titre"
          :aria-describedby="errors.titre ? 'err-titre' : undefined"
        />
        <p v-if="errors.titre" id="err-titre" class="error">{{ errors.titre }}</p>
        <label for="contact-description">Description *</label>
        <textarea
          id="contact-description"
          v-model="form.description"
          rows="6"
          maxlength="5000"
          required
          :aria-invalid="!!errors.description"
          :aria-describedby="errors.description ? 'err-description' : undefined"
        />
        <p v-if="errors.description" id="err-description" class="error">{{ errors.description }}</p>
        <p class="meta">
          Vos coordonnées servent uniquement à répondre à votre message.
          <RouterLink to="/confidentialite">Politique de confidentialité</RouterLink>
        </p>
        <button type="submit">{{ busy ? 'Envoi…' : 'Envoyer' }}</button>
      </fieldset>
    </form>
  </main>
</template>
