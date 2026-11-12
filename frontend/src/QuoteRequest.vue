<script setup>
import { nextTick, reactive, ref } from 'vue'
import { auth, refresh, request } from './auth'
const groups = [
 { title: 'Vos coordonnées', fields: [
  ['entreprise','Nom de l’entreprise','text',200,'organization'], ['nom','Nom','text',100,'family-name'],
  ['prenom','Prénom','text',100,'given-name'], ['email','Adresse électronique','email',254,'email'], ['telephone','Téléphone','tel',32,'tel']] },
 { title: 'Votre événement', fields: [
  ['lieu','Lieu','text',255,'off'], ['type_evenement','Type d’événement envisagé','text',100,'off'],
  ['date_souhaitee','Date souhaitée','date',10,'off'], ['participants_estimes','Nombre de participants estimé','number',10,'off']] }
]
const form = reactive(Object.fromEntries([...groups.flatMap(g => g.fields.map(f => [f[0], ''])), ['besoin','']]))
const newKey = () => [...crypto.getRandomValues(new Uint8Array(32))].map(b => b.toString(16).padStart(2,'0')).join('')
const key = newKey()
const busy = ref(false), errors = ref({}), message = ref(''), error = ref(''), uncertain = ref(false)
function edited(id) {
 delete errors.value[id]
 if (!Object.keys(errors.value).length && error.value === 'Vérifiez les champs signalés.') error.value = ''
}
async function submit() {
 busy.value = true; error.value = ''; errors.value = {}
 try {
  if (!auth.csrf) await refresh()
  const result = await request('/api/quote-requests', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':auth.csrf}, body:JSON.stringify({...form,submission_key:key}) })
  message.value = result.message; uncertain.value = false
  await nextTick(); document.getElementById('quote-success')?.focus()
 } catch(e) {
  errors.value = e.fields || {}
  if(e.status === 422) error.value = 'Vérifiez les champs signalés.'
  else if(e.status === 429) error.value = 'Trop de demandes. Réessayez dans quinze minutes.'
  else if(e.status === 403) { error.value = 'La session a expiré. Réessayez avec les mêmes informations.'; try { await refresh() } catch { /* Garder la saisie. */ } }
  else if(e.status === 409) { error.value = 'Cette tentative contient des informations différentes. Réessayez avec les informations de votre premier envoi.'; uncertain.value = true }
  else { error.value = 'Nous n’avons pas pu confirmer l’enregistrement. Vos informations sont conservées dans ce formulaire. Réessayez sans les modifier pour éviter un doublon.'; uncertain.value = true }
  busy.value = false
  await nextTick(); (document.getElementById(Object.keys(errors.value)[0]) || document.getElementById('quote-errors'))?.focus()
 } finally { busy.value = false }
}
</script>
<template>
 <main class="panel">
  <h1>Parlons de votre événement</h1>
  <p>Décrivez votre projet pour que Chloé puisse vous recontacter et étudier votre demande de devis.</p>
  <section v-if="message" id="quote-success" tabindex="-1" role="status">
   <h2>Votre demande est enregistrée</h2><p class="preserve-text">{{ message }}</p><RouterLink to="/">Retour à l’accueil</RouterLink>
  </section>
  <form v-else @submit.prevent="submit" novalidate :aria-busy="busy">
   <p>Tous les champs sont obligatoires.</p>
   <div v-if="error" id="quote-errors" class="error" role="alert" tabindex="-1">{{ error }}</div>
   <fieldset v-for="group in groups" :key="group.title" :disabled="busy || uncertain">
    <legend>{{ group.title }}</legend>
    <div class="quote-grid">
     <div v-for="[id,label,type,max,autocomplete] in group.fields" :key="id">
      <label :for="id">{{ label }} *</label>
      <input :id="id" v-model="form[id]" @input="edited(id)" :type="type" :maxlength="max" :autocomplete="autocomplete" required :min="type === 'number' ? 1 : undefined" :step="type === 'number' ? 1 : undefined" :aria-invalid="!!errors[id]" :aria-describedby="errors[id] ? id+'-error' : undefined">
      <p v-if="errors[id]" :id="id+'-error'" class="error">{{ errors[id] }}</p>
     </div>
    </div>
   </fieldset>
   <label for="besoin">Votre besoin en quelques mots *</label>
   <textarea id="besoin" v-model="form.besoin" @input="edited('besoin')" rows="5" maxlength="5000" required :disabled="busy || uncertain" :aria-invalid="!!errors.besoin" :aria-describedby="errors.besoin ? 'besoin-error' : undefined" />
   <p v-if="errors.besoin" id="besoin-error" class="error">{{ errors.besoin }}</p>
   <p v-if="busy" role="status">Enregistrement de votre demande…</p>
   <p><button :disabled="busy">{{ busy ? 'Envoi…' : uncertain ? 'Vérifier et réessayer' : 'Envoyer ma demande' }}</button></p>
  </form>
 </main>
</template>
<style scoped>
fieldset { border:0; padding:0; margin:28px 0; min-width:0; }
legend { font-size:22px; font-weight:700; color:#142D43; }
.quote-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:0 24px; }
textarea { width:100%; padding:12px; font:inherit; border:1px solid #718391; border-radius:4px; resize:vertical; }
.preserve-text { white-space:pre-wrap; overflow-wrap:anywhere; }
[aria-invalid="true"] { border:2px solid #B42318; }
@media(max-width:600px) { .quote-grid { grid-template-columns:1fr; } }
</style>
