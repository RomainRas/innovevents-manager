<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue'
import { auth, refresh, request } from './auth'
const fields = [
  { key: 'prenom', label: 'Prénom', max: 100, required: true, auto: 'given-name' },
  { key: 'nom', label: 'Nom', max: 100, required: true, auto: 'family-name' },
  { key: 'entreprise', label: 'Entreprise', max: 200, required: true, client: true, auto: 'organization' },
  { key: 'telephone', label: 'Téléphone', max: 32, required: true, client: true, auto: 'tel', type: 'tel' },
  { key: 'adresse_ligne1', label: 'Adresse', max: 255, client: true, auto: 'address-line1' },
  { key: 'adresse_ligne2', label: 'Complément d’adresse', max: 255, client: true, auto: 'address-line2' },
  { key: 'code_postal', label: 'Code postal', max: 20, client: true, auto: 'postal-code' },
  { key: 'ville', label: 'Ville', max: 120, client: true, auto: 'address-level2' },
]
const form = reactive({}), profile = ref(null), deletion = ref(null)
const loading = ref(true), busy = ref(''), error = ref(''), notice = ref(''), errors = ref({})
const confirmDeletion = ref(false), reason = ref(''), deletionError = ref(''), deletionFieldError = ref('')
const day = v => v ? new Date(v.replace(' ', 'T').slice(0, 19) + 'Z').toLocaleDateString('fr-FR', { timeZone: 'Europe/Paris' }) : ''
function apply(data) {
  profile.value = data.profil; deletion.value = data.suppression
  for (const f of fields) if (!f.client || data.profil.fiche_client) form[f.key] = data.profil[f.key] ?? ''
}
onMounted(async () => {
  try { apply(await request('/api/me/profile')) } catch { error.value = 'Vos informations sont indisponibles pour le moment.' } finally { loading.value = false }
})
const post = (path, body) => request(path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf }, body: JSON.stringify(body) })
async function save() {
  busy.value = 'profile'; error.value = ''; notice.value = ''; errors.value = {}
  try {
    apply(await post('/api/me/profile-save', form)); await refresh()
    notice.value = 'Vos informations ont été enregistrées.'
    busy.value = ''; await nextTick(); document.getElementById('profile-notice')?.focus()
  } catch (e) {
    errors.value = e.fields || {}
    error.value = e.status === 422 ? 'Vérifiez les champs signalés.' : e.status === 401 || e.status === 403 ? 'Votre session a expiré. Reconnectez-vous puis enregistrez à nouveau.' : 'L’enregistrement n’a pas pu être confirmé. Vos saisies sont conservées : réessayez.'
    // Réactiver le formulaire avant d'y placer le focus.
    busy.value = ''; await nextTick()
    const first = Object.keys(errors.value)[0]; document.getElementById(first ? 'profile-' + first : 'profile-error')?.focus()
  } finally { busy.value = '' }
}
async function requestDeletion() {
  deletionError.value = ''; deletionFieldError.value = ''
  if (!confirmDeletion.value) { deletionFieldError.value = 'Cochez la case pour confirmer votre demande.'; await nextTick(); document.getElementById('deletion-confirm')?.focus(); return }
  busy.value = 'deletion'
  try {
    apply(await post('/api/me/deletion-request', { confirmation: true, motif: reason.value }))
    busy.value = ''; await nextTick(); document.getElementById('deletion-status')?.focus()
  } catch (e) {
    if (e.status === 409) { apply(await request('/api/me/profile').catch(() => ({ profil: profile.value, suppression: deletion.value }))); busy.value = ''; await nextTick(); document.getElementById('deletion-status')?.focus(); return }
    deletionError.value = e.fields?.motif || e.fields?.confirmation || (e.status === 401 || e.status === 403 ? 'Votre session a expiré. Reconnectez-vous pour envoyer la demande.' : 'La demande n’a pas pu être confirmée. Réessayez dans quelques instants.')
    busy.value = ''; await nextTick(); document.getElementById('deletion-error')?.focus()
  } finally { busy.value = '' }
}
</script>
<template>
  <main class="panel narrow-form">
    <p><RouterLink to="/espace">← Retour à mon espace</RouterLink></p>
    <h1>Mes informations</h1>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" id="profile-error" class="error" role="alert" tabindex="-1">{{ error }}</p>
    <template v-if="profile">
      <form @submit.prevent="save" novalidate :aria-busy="busy === 'profile'">
        <p>Les champs marqués * sont obligatoires.</p>
        <p class="meta">Adresse électronique de connexion : <strong>{{ profile.email }}</strong>. Pour la changer, <RouterLink to="/contact">contactez-nous</RouterLink>.</p>
        <p v-if="notice" id="profile-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
        <fieldset :disabled="!!busy">
          <legend class="sr-only">Informations personnelles</legend>
          <template v-for="f in fields" :key="f.key">
            <template v-if="!f.client || profile.fiche_client">
              <label :for="'profile-' + f.key">{{ f.label }}{{ f.required ? ' *' : '' }}</label>
              <input :id="'profile-' + f.key" v-model="form[f.key]" :type="f.type || 'text'" :maxlength="f.max" :required="f.required" :autocomplete="f.auto"
                :aria-invalid="!!errors[f.key]" :aria-describedby="errors[f.key] ? 'profile-err-' + f.key : undefined">
              <p v-if="errors[f.key]" :id="'profile-err-' + f.key" class="error">{{ errors[f.key] }}</p>
            </template>
          </template>
          <button type="submit">{{ busy === 'profile' ? 'Enregistrement…' : 'Enregistrer mes informations' }}</button>
        </fieldset>
      </form>

      <section class="danger" aria-labelledby="deletion-title">
        <h2 id="deletion-title">Supprimer mon compte</h2>
        <div v-if="deletion" id="deletion-status" role="status" tabindex="-1">
          <p v-if="deletion.statut === 'en_attente'"><strong>Demande envoyée le {{ day(deletion.demandee_le) }}.</strong> Innov’Events traite votre demande de suppression totale de vos informations et de votre compte, puis vous en informe par courriel.</p>
          <p v-else>Votre demande du {{ day(deletion.demandee_le) }} a été traitée.</p>
        </div>
        <template v-else>
          <p>Vous pouvez demander la suppression totale de vos informations personnelles et de votre compte. L’équipe Innov’Events traite la demande et vous confirme la suppression par courriel. Les pièces que la loi impose de conserver (factures) sont gardées pendant la durée légale.</p>
          <p v-if="deletionError" id="deletion-error" class="error" role="alert" tabindex="-1">{{ deletionError }}</p>
          <fieldset :disabled="!!busy">
            <legend class="sr-only">Demande de suppression</legend>
            <label for="deletion-reason">Motif (facultatif)</label>
            <textarea id="deletion-reason" v-model="reason" rows="3" maxlength="2000"></textarea>
            <p class="check"><input id="deletion-confirm" v-model="confirmDeletion" type="checkbox" :aria-invalid="!!deletionFieldError" :aria-describedby="deletionFieldError ? 'deletion-confirm-error' : undefined">
              <label for="deletion-confirm">Je demande la suppression totale de mes informations et de mon compte.</label></p>
            <p v-if="deletionFieldError" id="deletion-confirm-error" class="error">{{ deletionFieldError }}</p>
            <button type="button" class="danger-button" @click="requestDeletion">{{ busy === 'deletion' ? 'Envoi…' : 'Envoyer la demande de suppression' }}</button>
          </fieldset>
        </template>
      </section>
    </template>
  </main>
</template>
<style scoped>
form button[type=submit] { margin-top: 24px }
.danger { border-top: 1px solid #CBD5E1; margin-top: 32px; padding-top: 8px }
.check { display: flex; gap: 8px; align-items: flex-start; margin: 16px 0 }
.check input { width: 24px; height: 24px; flex: none; margin: 0 }
.check label { margin: 0; font-weight: 400 }
.danger-button { background: #B42318 }
.danger-button:hover { background: #8F1C13 }
.success { color: #0B6B3A; font-weight: 600 }
textarea { width: 100%; padding: 12px; border: 1px solid #718391; border-radius: 4px; font: inherit }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap }
</style>
