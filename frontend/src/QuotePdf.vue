<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { auth, request } from './auth'
const props=defineProps({eventId:String,revision:Number,disabled:Boolean})
const emit=defineEmits(['locked','generated'])
const doc=ref(null),busy=ref(false),uncertain=ref(false),error=ref(''),notice=ref('')
onMounted(async()=>{try{const d=await request('/api/admin/quote-pdf-status?event_id='+props.eventId);doc.value=d.ready?d.document_id:null}catch{error.value='Impossible de vérifier le PDF existant.'}})
async function generate(){busy.value=true;emit('locked',true);error.value='';notice.value='';doc.value=null
 try{const d=await request('/api/admin/quote-pdf-generate',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':auth.csrf},body:JSON.stringify({event_id:props.eventId,revision:props.revision})});doc.value=d.document_id;uncertain.value=false;notice.value='PDF prêt à télécharger. Il n’a pas été envoyé.';emit('generated');await nextTick();document.getElementById('pdf-result')?.focus()}
 catch(e){if(e.status===422){error.value='Complétez les coordonnées du client, le nom, le lieu, les dates et l’image de l’événement, puis enregistrez les prestations et le taux de TVA.';uncertain.value=false}else if(e.status===409){error.value='Le devis a changé ou ne peut plus être généré. Rechargez la fiche.';uncertain.value=false}else if(e.status===401||e.status===403){error.value='Votre session ou vos droits ne permettent plus cette action.';uncertain.value=false}else{error.value='Génération non confirmée. Réessayez pour retrouver le PDF.';uncertain.value=true}await nextTick();document.getElementById('pdf-error')?.focus()}
 finally{busy.value=false;emit('locked',uncertain.value)}
}
async function download(){busy.value=true;emit('locked',true);error.value=''
 try{const response=await fetch('/api/admin/quote-pdf?id='+doc.value,{credentials:'same-origin',signal:AbortSignal.timeout(15000)});if(!response.ok){if(response.status===409||response.status===422){doc.value=null;throw Error('Ce PDF est ancien ou le dossier est incomplet. Générez une nouvelle version.')}throw Error('Téléchargement indisponible. Vérifiez votre session et réessayez.')}const blob=await response.blob();const url=URL.createObjectURL(blob);const a=document.createElement('a');a.href=url;a.download='devis-'+props.eventId+'.pdf';a.click();setTimeout(()=>URL.revokeObjectURL(url),60000)}catch(e){error.value=e.message;await nextTick();document.getElementById('pdf-error')?.focus()}finally{busy.value=false;emit('locked',false)}
}
</script>
<template><section><h2>Devis PDF</h2><p>Le PDF utilise les informations enregistrées. Le télécharger ne l’envoie pas au client.</p><p v-if="error" id="pdf-error" tabindex="-1" role="alert" class="error">{{ error }}</p><p v-if="notice" id="pdf-result" tabindex="-1" role="status">{{ notice }}</p><p><button :disabled="disabled||busy" @click="generate">{{ busy?'Traitement…':uncertain?'Vérifier et retrouver le PDF':'Générer le PDF' }}</button></p><p v-if="doc"><button :disabled="disabled||busy" @click="download">Télécharger le PDF</button></p></section></template>
