<script setup>
import { nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { auth, request, refresh } from './auth'
const route=useRoute(),loading=ref(true),busy=ref(false),error=ref(''),errors=ref({}),confirmed=ref(false),uncertain=ref(false),clientId=ref(null),canConvert=ref(false)
const fields=[['entreprise','Entreprise','organization',200],['contact_nom','Nom du contact','family-name',100],['contact_prenom','Prénom du contact','given-name',100],['email','Adresse électronique','email',254],['telephone','Téléphone','tel',32]]
const form=reactive(Object.fromEntries(fields.map(f=>[f[0],''])))
onMounted(async()=>{try{
 const p=(await request('/api/admin/prospect?'+new URLSearchParams({id:route.params.id}))).prospect
 if(p.statut==='converti'){clientId.value=String(p.client_id);return}
 if(p.statut!=='a_contacter'){error.value='Cette demande ne peut plus être convertie.';return}
 Object.assign(form,{entreprise:p.entreprise,contact_nom:p.nom,contact_prenom:p.prenom,email:p.email,telephone:p.telephone});canConvert.value=true
}catch(e){error.value=e.status===403?'Accès réservé à l’administratrice.':'Impossible de charger cette demande.'}finally{loading.value=false}})
async function submit(){
 busy.value=true;error.value='';errors.value={}
 try{
  const data=await request('/api/admin/prospect-conversion',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':auth.csrf},body:JSON.stringify({id:route.params.id,...form})})
  clientId.value=data.client_id;canConvert.value=false;uncertain.value=false
  await nextTick();document.getElementById('conversion-success')?.focus()
 }catch(e){
  errors.value=e.fields||{}
  if(e.status===422)error.value='Vérifiez les champs signalés.'
  else if(e.status===409){error.value='Cette demande a déjà été traitée avec une autre décision. Revenez à sa fiche pour consulter son état.';canConvert.value=false}
  else if(e.status===401||e.status===403){error.value='Votre session ou vos droits ne permettent plus cette action. Reconnectez-vous si nécessaire.';try{await refresh()}catch{/* Conserver la saisie. */}}
  else{uncertain.value=true;error.value='Nous n’avons pas pu confirmer la conversion. Réessayez avec les mêmes informations pour retrouver le résultat sans créer une seconde fiche.'}
  busy.value=false;await nextTick();(document.getElementById(Object.keys(errors.value)[0])||document.getElementById('conversion-error'))?.focus()
 }finally{busy.value=false}
}
</script>
<template><main class="panel narrow"><RouterLink :to="'/prospects/'+route.params.id">Retour au prospect</RouterLink><h1>Convertir en client</h1>
 <p v-if="loading" role="status">Chargement des coordonnées…</p><p v-if="error" id="conversion-error" role="alert" tabindex="-1" class="error">{{ error }}</p>
 <section v-if="clientId" id="conversion-success" role="status" tabindex="-1"><h2>La fiche client est créée</h2><RouterLink :to="'/clients/'+clientId">Consulter la fiche client</RouterLink></section>
 <form v-if="canConvert" @submit.prevent="submit" novalidate :aria-busy="busy"><p>Les coordonnées du prospect sont reprises ci-dessous. Vérifiez-les et corrigez-les si nécessaire. Tous les champs sont obligatoires.</p>
 <div v-for="[id,label,autocomplete,max] in fields" :key="id"><label :for="id">{{ label }}</label><input :id="id" v-model="form[id]" :type="id==='email'?'email':id==='telephone'?'tel':'text'" :autocomplete="autocomplete" :maxlength="max" required :disabled="busy || uncertain" :aria-invalid="!!errors[id]" :aria-describedby="errors[id]?id+'-error':undefined"><p v-if="errors[id]" :id="id+'-error'" class="error">{{ errors[id] }}</p></div>
 <label class="confirm"><input type="checkbox" v-model="confirmed" :disabled="busy || uncertain">Je confirme la création de cette fiche client.</label><button :disabled="busy || !confirmed">{{ busy?'Conversion…':uncertain?'Vérifier et réessayer':'Créer la fiche client' }}</button>
 </form></main></template>
<style scoped>.confirm{display:flex;gap:12px;align-items:center;font-weight:400}.confirm input{width:22px;min-height:22px;flex-shrink:0}[aria-invalid="true"]{border:2px solid #B42318}</style>
