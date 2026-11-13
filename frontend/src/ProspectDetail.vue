<script setup>
import { nextTick, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { auth, refresh, request } from './auth'
const route=useRoute(),prospect=ref(null),loading=ref(true),busy=ref(false),error=ref(''),message=ref(''),confirmed=ref(false),saved=ref(false),uncertain=ref(false)
const labels={a_contacter:'À contacter',echoue:'Échoué',converti:'Converti'}
const fields={nom:'Nom',prenom:'Prénom',email:'Adresse électronique',telephone:'Téléphone',lieu:'Lieu',type_evenement:'Type d’événement',date_souhaitee:'Date souhaitée',participants_estimes:'Participants estimés',besoin:'Besoin'}
async function load(){loading.value=true;error.value='';try{prospect.value=(await request('/api/admin/prospect?'+new URLSearchParams({id:route.params.id}))).prospect}catch(e){error.value=e.status===403?'Cet espace est réservé à l’administratrice.':e.status===404?'Demande introuvable.':e.status===401?'Votre session a expiré. Reconnectez-vous.':'Chargement impossible. Réessayez.'}finally{loading.value=false}}
async function submit(){
 busy.value=true;error.value=''
 try {
  await request('/api/admin/prospect-failure',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':auth.csrf},body:JSON.stringify({id:route.params.id,message:message.value})})
  prospect.value.statut='echoue';prospect.value.message_echec=message.value.trim();saved.value=true;uncertain.value=false
  await nextTick();document.getElementById('qualification-result')?.focus()
 } catch(e){
  if(e.status===422)error.value='Saisissez un message de 1 à 2 000 caractères.'
  else if(e.status===409){await load();error.value='Cette demande a déjà été traitée. Consultez son état actualisé.'}
  else if(e.status===401||e.status===403){error.value='La session ou vos droits ne permettent plus cette action. Reconnectez-vous si nécessaire.';try{await refresh()}catch{/* Préserver le texte. */}}
  else{uncertain.value=true;error.value='Nous n’avons pas pu confirmer le résultat. Réessayez avec le même message : aucun second courriel ne sera ajouté pour cette action déjà enregistrée.'}
  busy.value=false;await nextTick();document.getElementById('qualification-error')?.focus()
 }finally{busy.value=false}
}
onMounted(load)
</script>
<template><main class="panel"><RouterLink to="/prospects">Retour aux demandes</RouterLink>
 <p v-if="loading" role="status">Chargement…</p><p v-if="error" id="qualification-error" role="alert" tabindex="-1" class="error">{{ error }}</p>
 <template v-if="prospect"><h1>{{ prospect.entreprise }}</h1><p>Statut : <strong>{{ labels[prospect.statut] }}</strong></p>
 <dl><template v-for="(label,key) in fields" :key="key"><dt>{{ label }}</dt><dd>{{ prospect[key] }}</dd></template></dl>
 <section v-if="prospect.statut === 'a_contacter'"><h2>Le projet ne peut pas aboutir ?</h2><p>Après votre échange avec le prospect, expliquez pourquoi l’agence ne peut pas donner suite. Ce message lui sera envoyé par courriel.</p>
 <form @submit.prevent="submit" :aria-busy="busy"><label for="failure-message">Message au prospect</label><textarea id="failure-message" v-model="message" maxlength="2000" rows="6" required :disabled="busy || uncertain" />
 <label class="confirmation"><input type="checkbox" v-model="confirmed" required :disabled="busy || uncertain">Je confirme le classement en « échoué » et l’envoi de ce message.</label>
 <button :disabled="busy || !confirmed">{{ busy ? 'Enregistrement…' : uncertain ? 'Vérifier et réessayer' : 'Classer en échoué et notifier' }}</button></form></section>
 <section v-if="prospect.statut === 'echoue'"><h2>Message enregistré pour le prospect</h2><p class="message">{{ prospect.message_echec }}</p></section>
 <p v-if="saved" id="qualification-result" role="status" tabindex="-1">Demande classée en échoué. Le message a été mis en attente d’envoi.</p>
 </template></main></template>
<style scoped>dt{font-weight:700;margin-top:16px}dd{margin:4px 0 0;white-space:pre-wrap;overflow-wrap:anywhere}textarea{width:100%;padding:12px;font:inherit;border:1px solid #718391;border-radius:4px;resize:vertical}.confirmation{display:flex;gap:12px;align-items:center;font-weight:400}.confirmation input{width:22px;min-height:22px;flex-shrink:0}.message{white-space:pre-wrap;overflow-wrap:anywhere}</style>
