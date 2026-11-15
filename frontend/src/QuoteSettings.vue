<script setup>
// Paramètres des demandes de devis (administratrice) : message de remerciement affiché après l'envoi d'une demande.
import { onMounted, ref } from 'vue'
import { auth, request } from './auth'

const message = ref(''),
  error = ref(''),
  saved = ref(false),
  busy = ref(true)

onMounted(async () => {
  try {
    message.value = (await request('/api/quote-request/config')).message
  } catch {
    error.value = 'Chargement impossible.'
  } finally {
    busy.value = false
  }
})

async function save() {
  busy.value = true
  error.value = ''
  saved.value = false
  try {
    await request('/api/admin/quote-thanks', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify({ message: message.value }),
    })
    saved.value = true
  } catch (e) {
    error.value =
      e.status === 403
        ? 'Accès réservé à l’administratrice.'
        : 'Enregistrement impossible. Vérifiez le texte et réessayez.'
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <main class="panel">
    <h1>Remerciement des demandes de devis</h1>
    <form v-if="auth.user?.role === 'administrateur'" @submit.prevent="save">
      <label for="thanks">Message affiché après une demande</label
      ><textarea id="thanks" v-model="message" required maxlength="2000" rows="6" :disabled="busy" />
      <p>Le message est affiché comme du texte, sans mise en forme HTML.</p>
      <button :disabled="busy">Enregistrer</button>
    </form>
    <p v-else>Accès réservé à l’administratrice.</p>
    <p v-if="error" role="alert" class="error">{{ error }}</p>
    <p v-if="saved" role="status">Message enregistré.</p>
  </main>
</template>
<style scoped>
textarea {
  width: 100%;
  padding: 12px;
  font: inherit;
  border: 1px solid #718391;
  border-radius: 4px;
}
</style>
