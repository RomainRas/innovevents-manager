<script setup>
// Page « Mes informations » : modification de ses coordonnées et demande de suppression du compte.
// Les champs de l'entreprise et de l'adresse ne s'affichent que pour un client rattaché à une fiche.
import { nextTick, onMounted, reactive, ref } from 'vue'
import { auth, refresh, request } from './auth'

// Champs du profil ; client: true = champ de la fiche client (absent pour l'équipe).
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

const form = reactive({}),
  profile = ref(null),
  deletion = ref(null)
const loading = ref(true),
  busy = ref(''),
  error = ref(''),
  notice = ref(''),
  errors = ref({})

// Demande de suppression : case de confirmation obligatoire et motif facultatif.
const confirmDeletion = ref(false),
  reason = ref(''),
  deletionError = ref(''),
  deletionFieldError = ref('')
const day = v =>
  v ? new Date(v.replace(' ', 'T').slice(0, 19) + 'Z').toLocaleDateString('fr-FR', { timeZone: 'Europe/Paris' }) : ''

/** Recopie dans le formulaire le profil et l'état de la demande de suppression renvoyés par l'API. */
function apply(data) {
  profile.value = data.profil
  deletion.value = data.suppression
  for (const f of fields) if (!f.client || data.profil.fiche_client) form[f.key] = data.profil[f.key] ?? ''
}

onMounted(async () => {
  try {
    apply(await request('/api/me/profile'))
  } catch {
    error.value = 'Vos informations sont indisponibles pour le moment.'
  } finally {
    loading.value = false
  }
})

// Écriture JSON vers l'API avec le jeton CSRF.
const post = (path, body) =>
  request(path, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
    body: JSON.stringify(body),
  })

/** Enregistre les coordonnées ; la session est relue pour mettre à jour le prénom affiché dans l'en-tête. */
async function save() {
  busy.value = 'profile'
  error.value = ''
  notice.value = ''
  errors.value = {}
  try {
    apply(await post('/api/me/profile-save', form))
    await refresh()
    notice.value = 'Vos informations ont été enregistrées.'
    busy.value = ''
    await nextTick()
    document.getElementById('profile-notice')?.focus()
  } catch (e) {
    errors.value = e.fields || {}
    error.value =
      e.status === 422
        ? 'Vérifiez les champs signalés.'
        : e.status === 401 || e.status === 403
          ? 'Votre session a expiré. Reconnectez-vous puis enregistrez à nouveau.'
          : 'L’enregistrement n’a pas pu être confirmé. Vos saisies sont conservées : réessayez.'
    // Réactiver le formulaire avant d'y placer le focus.
    busy.value = ''
    await nextTick()
    const first = Object.keys(errors.value)[0]
    document.getElementById(first ? 'profile-' + first : 'profile-error')?.focus()
  } finally {
    busy.value = ''
  }
}

/**
 * Envoie la demande de suppression à l'agence, qui la traite (les devis et factures sont conservés).
 * Une demande déjà en cours (409) affiche simplement son état.
 */
async function requestDeletion() {
  deletionError.value = ''
  deletionFieldError.value = ''
  if (!confirmDeletion.value) {
    deletionFieldError.value = 'Cochez la case pour confirmer votre demande.'
    await nextTick()
    document.getElementById('deletion-confirm')?.focus()
    return
  }
  busy.value = 'deletion'
  try {
    apply(await post('/api/me/deletion-request', { confirmation: true, motif: reason.value }))
    busy.value = ''
    await nextTick()
    document.getElementById('deletion-status')?.focus()
  } catch (e) {
    if (e.status === 409) {
      apply(await request('/api/me/profile').catch(() => ({ profil: profile.value, suppression: deletion.value })))
      busy.value = ''
      await nextTick()
      document.getElementById('deletion-status')?.focus()
      return
    }
    deletionError.value =
      e.fields?.motif ||
      e.fields?.confirmation ||
      (e.status === 401 || e.status === 403
        ? 'Votre session a expiré. Reconnectez-vous pour envoyer la demande.'
        : 'La demande n’a pas pu être confirmée. Réessayez dans quelques instants.')
    busy.value = ''
    await nextTick()
    document.getElementById('deletion-error')?.focus()
  } finally {
    busy.value = ''
  }
}
</script>
<template>
  <main class="work-page">
    <div class="work-card profile-card">
      <p><RouterLink class="back-link" to="/espace">← Retour à mon espace</RouterLink></p>
      <h1 class="profile-title">Mes informations</h1>
      <p v-if="loading" role="status">Chargement…</p>
      <p v-if="error" id="profile-error" class="error" role="alert" tabindex="-1">{{ error }}</p>
      <template v-if="profile">
        <!-- Coordonnées modifiables -->
        <form @submit.prevent="save" novalidate :aria-busy="busy === 'profile'">
          <p>Les champs marqués <span class="req">*</span> sont obligatoires.</p>
          <p class="info">
            <svg aria-hidden="true" viewBox="0 0 24 24">
              <circle cx="12" cy="12" r="10" />
              <path d="M12 11v6M12 7.5v.5" /></svg
            ><span
              >Adresse électronique de connexion : <strong>{{ profile.email }}</strong
              >. Pour la changer, <RouterLink to="/contact">contactez-nous</RouterLink>.</span
            >
          </p>
          <p v-if="notice" id="profile-notice" class="success" role="status" tabindex="-1">{{ notice }}</p>
          <fieldset class="fields" :disabled="!!busy">
            <legend class="sr-only">Informations personnelles</legend>
            <template v-for="f in fields" :key="f.key">
              <template v-if="!f.client || profile.fiche_client">
                <label :for="'profile-' + f.key">{{ f.label }}<span v-if="f.required" class="req"> *</span></label>
                <input
                  :id="'profile-' + f.key"
                  v-model="form[f.key]"
                  :type="f.type || 'text'"
                  :maxlength="f.max"
                  :required="f.required"
                  :autocomplete="f.auto"
                  :aria-invalid="!!errors[f.key]"
                  :aria-describedby="errors[f.key] ? 'profile-err-' + f.key : undefined"
                />
                <p v-if="errors[f.key]" :id="'profile-err-' + f.key" class="error">{{ errors[f.key] }}</p>
              </template>
            </template>
            <button type="submit" class="save">
              <svg aria-hidden="true" viewBox="0 0 24 24">
                <path d="M5 3h11l3 3v15H5z" />
                <path d="M8 3v6h8V3M8 21v-7h8v7" /></svg
              >{{ busy === 'profile' ? 'Enregistrement…' : 'Enregistrer mes informations' }}
            </button>
          </fieldset>
        </form>

        <!-- Demande de suppression du compte -->
        <section class="danger" aria-labelledby="deletion-title">
          <h2 id="deletion-title">
            <svg aria-hidden="true" viewBox="0 0 24 24">
              <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" /></svg
            >Supprimer mon compte
          </h2>
          <div v-if="deletion" id="deletion-status" role="status" tabindex="-1">
            <p v-if="deletion.statut === 'en_attente'">
              <strong>Demande envoyée le {{ day(deletion.demandee_le) }}.</strong> Innov’Events traite votre demande de
              suppression totale de vos informations et de votre compte, puis vous en informe par courriel.
            </p>
            <p v-else>Votre demande du {{ day(deletion.demandee_le) }} a été traitée.</p>
          </div>
          <template v-else>
            <p>
              Vous pouvez demander la suppression totale de vos informations personnelles et de votre compte. L’équipe
              Innov’Events traite la demande et vous confirme la suppression par courriel. Les pièces que la loi impose
              de conserver (factures) sont gardées pendant la durée légale.
            </p>
            <p v-if="deletionError" id="deletion-error" class="error" role="alert" tabindex="-1">{{ deletionError }}</p>
            <fieldset :disabled="!!busy">
              <legend class="sr-only">Demande de suppression</legend>
              <label for="deletion-reason">Motif (facultatif)</label>
              <textarea id="deletion-reason" v-model="reason" rows="3" maxlength="2000"></textarea>
              <p class="check">
                <input
                  id="deletion-confirm"
                  v-model="confirmDeletion"
                  type="checkbox"
                  :aria-invalid="!!deletionFieldError"
                  :aria-describedby="deletionFieldError ? 'deletion-confirm-error' : undefined"
                />
                <label for="deletion-confirm"
                  >Je demande la suppression totale de mes informations et de mon compte.</label
                >
              </p>
              <p v-if="deletionFieldError" id="deletion-confirm-error" class="error">{{ deletionFieldError }}</p>
              <button type="button" class="danger-button" @click="requestDeletion">
                <svg aria-hidden="true" viewBox="0 0 24 24">
                  <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                </svg>
                {{ busy === 'deletion' ? 'Envoi…' : 'Envoyer la demande de suppression' }}
              </button>
            </fieldset>
          </template>
        </section>
      </template>
    </div>
  </main>
</template>
<style scoped>
/* Mes informations : libellés à gauche des champs, encadré d'information bleu, zone de suppression rouge. */
.profile-card {
  max-width: 1100px;
}
.profile-title {
  margin: 4px 0 16px;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: clamp(40px, 5vw, 56px);
  line-height: 1.1;
  color: var(--encre);
}
.profile-title::after {
  content: '';
  display: block;
  width: 90px;
  height: 5px;
  margin-top: 6px;
  border-radius: 3px;
  background: var(--jaune);
  transform: rotate(-3deg);
}
.info {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 18px;
  border: 1px solid #b8c6ef;
  border-radius: 6px;
  background: #e3e9fa;
}
svg {
  flex: none;
  width: 24px;
  height: 24px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}
.info svg {
  color: var(--bleu);
}
.fields {
  display: grid;
  grid-template-columns: minmax(150px, 220px) minmax(0, 1fr);
  gap: 12px 24px;
  align-items: center;
  margin-top: 24px;
}
.fields > legend + * {
  grid-column: auto;
}
.fields label {
  margin: 0;
}
.fields .error {
  grid-column: 2;
  margin: -6px 0 0;
}
.fields input {
  min-height: 44px;
}
.save {
  grid-column: 1 / -1;
  justify-self: center;
  display: inline-flex;
  align-items: center;
  gap: 12px;
  margin-top: 16px !important;
  background: var(--bleu) !important;
}
.save:hover {
  background: #142e8f !important;
}
.danger {
  margin-top: 36px;
  padding: 20px 24px;
  border: 1px solid var(--rouge);
  border-radius: 6px;
  background: var(--erreur-fond);
}
.danger h2 {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 0 0 8px;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: 2rem;
  color: var(--erreur);
}
.danger h2 svg {
  width: 36px;
  height: 36px;
}
.danger fieldset > label {
  margin-top: 12px;
}
.check {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  margin: 16px 0;
}
.check input {
  width: 24px;
  height: 24px;
  flex: none;
  margin: 0;
}
.check label {
  margin: 0;
  font-weight: 700;
}
.danger-button {
  display: inline-flex;
  align-items: center;
  gap: 10px;
}
@media (max-width: 700px) {
  .fields {
    grid-template-columns: minmax(0, 1fr);
    gap: 6px;
  }
  .fields .error {
    grid-column: 1;
  }
  .fields input {
    margin-bottom: 10px;
  }
}
</style>
