<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { request, auth } from './auth'
import QuotePdf from './QuotePdf.vue'
import QuoteTransmission from './QuoteTransmission.vue'
const pdfLocked=ref(false),sendLocked=ref(false),pdfKey=ref(0)
const route=useRoute(),loading=ref(true),busy=ref(false),uncertain=ref(false),error=ref(''),errors=ref({}),notice=ref(''),event=ref(null),revision=ref(0),editable=ref(true),reference=ref(''),rate=ref(''),lines=ref([{libelle:'',montant_ht:''}]),totals=ref(null)
let pending=null
const counterKey=ref(0)
async function load(){loading.value=true;error.value='';try{const d=await request('/api/admin/quote-draft?event_id='+route.params.id);event.value=d.event;if(d.quote){const q=d.quote;revision.value=q.revision;reference.value=q.reference;rate.value=q.taux_tva??'';lines.value=q.prestations.length?q.prestations:[{libelle:'',montant_ht:''}];editable.value=q.editable;totals.value=q.total_ht===null?null:q}editable.value=editable.value&&d.event.statut==='brouillon'}catch{error.value='Impossible de consulter ce devis. Accès réservé à l’administratrice.'}finally{loading.value=false}}
onMounted(load)
async function counterCreated(){editable.value=true;await load();counterKey.value++;notice.value='Nouvelle version créée à partir de la précédente : modifiez les prestations, enregistrez, générez le PDF puis transmettez-la.';await nextTick();document.getElementById('quote-result')?.focus()}
function changed(){totals.value=null;notice.value=''}
async function save(){busy.value=true;error.value='';errors.value={};notice.value=''
 try{
  pending??=JSON.stringify({event_id:route.params.id,revision:revision.value,taux_tva:rate.value,prestations:lines.value})
  const d=await request('/api/admin/quote-draft-save',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':auth.csrf},body:pending})
  revision.value=d.revision;reference.value=d.reference;totals.value=d;lines.value=d.prestations;rate.value=d.taux_tva;pending=null;uncertain.value=false;notice.value='Prestations et totaux enregistrés.';await nextTick();document.getElementById('quote-result')?.focus()
 }catch(e){errors.value=e.fields||{}
  if(e.status===422){error.value='Vérifiez les champs signalés.';pending=null;uncertain.value=false}
  else if(e.status===409){error.value='Le devis ou l’événement a changé. Rechargez la page avant de continuer.';pending=null;uncertain.value=false}
  else if(e.status===401||e.status===403){error.value='Votre session ou vos droits ne permettent plus cette action.';pending=null;uncertain.value=false}
  else{error.value='Enregistrement non confirmé. Réessayez avec les mêmes prestations.';uncertain.value=true}
  busy.value=false;await nextTick();(document.getElementById(Object.keys(errors.value)[0])||document.getElementById('quote-error'))?.focus()
 }finally{busy.value=false}
}
</script>
<template><main class="panel"><RouterLink :to="'/evenements/'+route.params.id">Retour à l’événement</RouterLink><h1>Préparer le devis</h1><p v-if="loading" role="status">Chargement…</p><p v-if="error" id="quote-error" role="alert" tabindex="-1" class="error">{{ error }}</p><p v-if="notice" id="quote-result" role="status" tabindex="-1">{{ notice }}</p>
<template v-if="event"><p>{{ event.nom }} <span v-if="reference">— {{ reference }}</span></p><p>Montants en euros. La TVA est calculée sur le total HT, puis arrondie au centime. Renseignez le taux applicable : aucun taux n’est présélectionné.</p><p v-if="!editable">Ce devis n’est plus modifiable dans cet écran.</p>
<form @submit.prevent="save" novalidate :aria-busy="busy"><fieldset :disabled="busy||uncertain||pdfLocked||sendLocked||!editable"><legend>Prestations</legend><p v-if="errors.prestations" id="prestations" tabindex="-1" class="error">{{ errors.prestations }}</p>
<div v-for="(line,i) in lines" :key="i" class="line"><label :for="'libelle-'+i">Prestation {{ i+1 }} — libellé</label><input :id="'libelle-'+i" v-model="line.libelle" maxlength="255" required :aria-invalid="!!errors['libelle-'+i]" :aria-describedby="errors['libelle-'+i]?'libelle-error-'+i:undefined" @input="changed"><p v-if="errors['libelle-'+i]" :id="'libelle-error-'+i" class="error">{{ errors['libelle-'+i] }}</p>
<label :for="'montant-'+i">Montant HT {{ i+1 }} (€)</label><input :id="'montant-'+i" v-model="line.montant_ht" inputmode="decimal" required :aria-invalid="!!errors['montant-'+i]" :aria-describedby="errors['montant-'+i]?'montant-error-'+i:undefined" @input="changed"><p v-if="errors['montant-'+i]" :id="'montant-error-'+i" class="error">{{ errors['montant-'+i] }}</p><button type="button" :disabled="lines.length===1" :aria-label="'Supprimer la prestation '+(i+1)" @click="lines.splice(i,1);changed()">Supprimer</button></div>
<p><button type="button" :disabled="lines.length>=100" @click="lines.push({libelle:'',montant_ht:''});changed()">Ajouter une prestation</button></p><label for="taux_tva">Taux de TVA (%)</label><input id="taux_tva" v-model="rate" inputmode="decimal" required :aria-invalid="!!errors.taux_tva" :aria-describedby="errors.taux_tva?'taux-error':undefined" @input="changed"><p v-if="errors.taux_tva" id="taux-error" class="error">{{ errors.taux_tva }}</p></fieldset>
<p><button :disabled="busy||pdfLocked||sendLocked||!editable">{{ busy?'Enregistrement…':uncertain?'Vérifier et réessayer':'Calculer et enregistrer' }}</button></p></form>
<section v-if="totals" aria-labelledby="totals-title"><h2 id="totals-title">Totaux enregistrés</h2><dl><dt>Total HT</dt><dd>{{ totals.total_ht }} €</dd><dt>TVA</dt><dd>{{ totals.total_tva }} €</dd><dt>Total TTC</dt><dd>{{ totals.total_ttc }} €</dd></dl></section><p v-else>Les totaux seront affichés après validation et enregistrement.</p>
<QuotePdf v-if="totals && revision>0 && editable" :key="revision" :event-id="String(route.params.id)" :revision="revision" :disabled="busy||uncertain||sendLocked||!editable" @locked="pdfLocked=$event" @generated="pdfKey++" />
<QuoteTransmission v-if="totals && revision>0" :key="revision+'-'+pdfKey+'-'+counterKey" :event-id="String(route.params.id)" :disabled="busy||uncertain||pdfLocked" @locked="sendLocked=$event" @sent="editable=false" @counter="counterCreated" /></template></main></template>
<style scoped>fieldset{border:1px solid #CBD5E1;padding:16px;min-width:0}.line{border-bottom:1px solid #CBD5E1;padding-bottom:16px;margin-bottom:16px}dt{font-weight:700}dd{margin:4px 0 16px}[aria-invalid=true]{border:2px solid #B42318}</style>
