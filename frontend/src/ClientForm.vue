<script setup>
// Formulaire de fiche client (création et modification par l'administratrice).
import { nextTick, reactive, ref } from 'vue'
import { auth, request } from './auth'

const props = defineProps({
  initial: { type: Object, default: () => ({}) },
  clientId: { type: String, default: '' },
  submitLabel: { type: String, required: true },
})

const emit = defineEmits(['saved', 'cancel'])

// Champs de la fiche : clé, libellé, longueur maximale, obligatoire, type de saisie et autocomplete.
const fields = [
  { key: 'entreprise', label: 'Entreprise', max: 200, required: true, auto: 'organization' },
  { key: 'contact_prenom', label: 'Prénom du contact', max: 100, required: true, auto: 'off' },
  { key: 'contact_nom', label: 'Nom du contact', max: 100, required: true, auto: 'off' },
  { key: 'email', label: 'Adresse électronique', max: 254, required: true, type: 'email', auto: 'off' },
  { key: 'telephone', label: 'Téléphone', max: 32, required: true, type: 'tel', auto: 'off' },
  { key: 'adresse_ligne1', label: 'Adresse', max: 255, auto: 'off' },
  { key: 'adresse_ligne2', label: 'Complément d’adresse', max: 255, auto: 'off' },
  { key: 'code_postal', label: 'Code postal', max: 20, auto: 'off' },
  { key: 'ville', label: 'Ville', max: 120, auto: 'off' },
]

const form = reactive(Object.fromEntries(fields.map(f => [f.key, props.initial[f.key] ?? ''])))

// Préfixe des identifiants : le formulaire de création et celui de modification peuvent coexister sur une page.
const errors = ref({}),
  error = ref(''),
  busy = ref(false),
  prefix = props.clientId ? 'client-edit-' : 'client-new-'

/** Crée ou modifie la fiche selon qu'un identifiant est fourni ; en cas d'erreur, la saisie est conservée. */
async function submit() {
  busy.value = true
  errors.value = {}
  error.value = ''
  try {
    const path = props.clientId ? '/api/admin/client-save' : '/api/admin/client-create'
    const data = await request(path, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify(props.clientId ? { ...form, id: props.clientId } : form),
    })
    emit('saved', data.id)
  } catch (e) {
    errors.value = e.fields || {}
    error.value =
      e.status === 422
        ? 'Vérifiez les champs signalés.'
        : e.status === 404
          ? 'Cette fiche n’existe plus.'
          : e.status === 401 || e.status === 403
            ? 'Votre session a expiré. Reconnectez-vous.'
            : 'L’enregistrement n’a pas pu être confirmé. Vos saisies sont conservées : réessayez.'
    busy.value = false
    await nextTick()
    const first = Object.keys(errors.value)[0]
    document.getElementById(first ? prefix + first : prefix + 'error')?.focus()
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <form novalidate :aria-busy="busy" @submit.prevent="submit">
    <p v-if="error" :id="prefix + 'error'" class="error" role="alert" tabindex="-1">{{ error }}</p>
    <fieldset :disabled="busy">
      <legend class="sr-only">Fiche client</legend>
      <div class="grid">
        <div v-for="f in fields" :key="f.key">
          <label :for="prefix + f.key">{{ f.label }}{{ f.required ? ' *' : '' }}</label>
          <input
            :id="prefix + f.key"
            v-model="form[f.key]"
            :type="f.type || 'text'"
            :maxlength="f.max"
            :required="f.required"
            :autocomplete="f.auto"
            :aria-invalid="!!errors[f.key]"
            :aria-describedby="errors[f.key] ? prefix + 'err-' + f.key : undefined"
          />
          <p v-if="errors[f.key]" :id="prefix + 'err-' + f.key" class="error">{{ errors[f.key] }}</p>
        </div>
      </div>
      <p class="actions">
        <button type="submit">{{ busy ? 'Enregistrement…' : submitLabel }}</button
        ><button type="button" class="secondary" @click="emit('cancel')">Annuler</button>
      </p>
    </fieldset>
  </form>
</template>
<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 0 16px;
}
.actions {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 16px;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
</style>
