<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { auth, request } from './auth'
const props=defineProps({eventId:String,disabled:Boolean})
const emit=defineEmits(['sent','locked','counter'])
const state=ref(null),loading=ref(true),busy=ref(false),uncertain=ref(false),error=ref(''),notice=ref(''),confirming=ref(''),declared=ref(false)
let pendingKey=null,pendingMode=null
const modes={automatique:'Envoi par la plateforme',manuel:'Envoi manuel déclaré'}
const choices={accepte:'Accepté',refuse:'Refusé',modification:'Modification demandée'}
const statuses={brouillon:'Brouillon, non transmis',etude_client:'Étude côté client',modification:'Modification demandée par le client',accepte:'Accepté par le client',refuse:'Refusé par le client'}
const states={en_attente:'Courriel en attente d’envoi',confirme:'Confirmé',echec:'Échec de l’envoi',incertain:'Résultat incertain',en_cours:'En cours'}
const date=v=>v?new Date(v.replace(' ','T').slice(0,19)+'Z').toLocaleString('fr-FR',{timeZone:'Europe/Paris',dateStyle:'short',timeStyle:'short'}):''
async function load(){loading.value=true;try{state.value=await request('/api/admin/quote-transmission?event_id='+props.eventId)}catch{error.value='Impossible de consulter la transmission de ce devis.'}finally{loading.value=false}}
onMounted(load)
async function open(mode){confirming.value=mode;declared.value=false;error.value='';notice.value='';await nextTick();document.getElementById('send-confirm')?.focus()}
function cancel(){confirming.value='';document.getElementById('send-'+(pendingMode||'automatique'))?.focus()}
let counterPending=null
async function counter(){busy.value=true;emit('locked',true);error.value='';notice.value=''
 counterPending??=state.value.version
 try{await request('/api/admin/quote-counter-proposal',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':auth.csrf},body:JSON.stringify({event_id:props.eventId,version:counterPending})});counterPending=null;emit('counter')}
 catch(e){if(e.status&&e.status<500){counterPending=null;error.value=e.status===409?'La contre-proposition n’est plus possible : le devis a changé. Rechargez la page.':'Votre session ou vos droits ne permettent plus cette action.'}else error.value='Création non confirmée. Réessayez : aucune version en double ne sera créée.';await nextTick();document.getElementById('send-error')?.focus()}
 finally{busy.value=false;emit('locked',false)}}
async function send(){
 const mode=confirming.value||pendingMode;if(!mode)return
 if(mode==='manuel'&&!declared.value&&!uncertain.value){error.value='Cochez la déclaration pour confirmer l’envoi manuel.';await nextTick();document.getElementById('send-declared')?.focus();return}
 busy.value=true;emit('locked',true);error.value='';notice.value=''
 // Même clé tant que le résultat n'est pas connu : une nouvelle tentative ne crée pas un deuxième envoi.
 if(!pendingKey||pendingMode!==mode){pendingKey=crypto.randomUUID();pendingMode=mode}
 try{
  const d=await request('/api/admin/quote-send',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':auth.csrf},body:JSON.stringify({event_id:props.eventId,document_id:state.value.document_id,mode,cle:pendingKey,confirmation:mode==='manuel'})})
  pendingKey=null;pendingMode=null;uncertain.value=false;confirming.value=''
  notice.value=(mode==='manuel'?'Envoi manuel enregistré.':'Envoi demandé : le courriel avec le PDF est en file d’envoi.')+' Le devis est en étude côté client et n’est plus modifiable.'+(d.compte_cree?' Un espace client a été créé ; ses identifiants sont envoyés par courriel.':'')
  await load();emit('sent')
 }catch(e){
  const known={account_email_conflict:'Un compte existe déjà avec l’adresse du client. Il n’est pas rattaché automatiquement : vérifiez l’identité de son titulaire. Rien n’a été envoyé.',pdf_obsolete:'Le PDF n’est plus à jour. Générez-le à nouveau avant de le transmettre.',transmission_pending:'Un envoi est déjà en attente. Actualisez pour suivre son résultat.',quote_not_transmissible:'Ce devis ne peut plus être transmis depuis cet écran.',idempotency_conflict:'Cette demande ne correspond plus au devis. Rechargez la page.'}
  if(known[e.message]||e.status===422){error.value=known[e.message]||'Demande incomplète. Vérifiez la confirmation.';pendingKey=null;pendingMode=null;uncertain.value=false;confirming.value='';await load()}
  else if(e.status===401||e.status===403){error.value='Votre session ou vos droits ne permettent plus cette action.';pendingKey=null;uncertain.value=false}
  else{error.value='Résultat incertain : l’envoi a peut-être été enregistré. Vérifiez avant toute nouvelle demande ; aucun doublon ne sera créé.';uncertain.value=true;confirming.value=''}
  await nextTick();document.getElementById('send-error')?.focus()
 }finally{busy.value=false;emit('locked',uncertain.value)}
}
</script>
<template><section aria-labelledby="send-title"><h2 id="send-title">Transmission au client</h2>
<p v-if="loading" role="status">Chargement…</p>
<p v-if="error" id="send-error" tabindex="-1" role="alert" class="error">{{ error }}</p><p v-if="notice" id="send-result" tabindex="-1" role="status">{{ notice }}</p>
<template v-if="state&&!loading">
 <p><strong>Statut du devis :</strong> {{ statuses[state.statut]||'Aucun devis' }}<span v-if="state.version"> (version {{ state.version }})</span></p>
 <div v-if="state.decisions.length" class="decisions"><h3>Réponses du client</h3><ul><li v-for="d in state.decisions" :key="d.version">Version {{ d.version }}, le {{ date(d.decidee_le) }} : <strong>{{ choices[d.choix] }}</strong><template v-if="d.motif"><br>Motif : « {{ d.motif }} »</template></li></ul></div>
 <p v-if="state.statut==='modification'"><button type="button" :disabled="disabled||busy" @click="counter">{{ busy?'Création…':'Préparer une contre-proposition (version '+(state.version+1)+')' }}</button></p>
 <p v-if="state.statut==='accepte'">Le client a accepté ce devis : l’événement est passé au statut accepté.</p>
 <p v-if="state.statut==='brouillon'&&!state.document_id">Générez un PDF à jour avant de le transmettre.</p>
 <p v-if="state.statut==='brouillon'&&state.document_id&&!state.compte_client">Le client n’a pas encore d’espace : il sera créé à la transmission et ses identifiants envoyés à {{ state.destinataire }}, avec un mot de passe à changer à la première connexion.</p>
 <p v-if="state.en_attente">Un courriel est en attente d’envoi. <button type="button" class="secondary" :disabled="busy" @click="load">Actualiser</button></p>
 <p v-if="uncertain"><button :disabled="busy" @click="send">{{ busy?'Vérification…':'Vérifier le résultat' }}</button></p>
 <div v-if="state.document_id&&!state.en_attente&&!uncertain&&!confirming" class="actions">
  <button id="send-automatique" type="button" :disabled="disabled||busy" @click="open('automatique')">Envoyer par la plateforme…</button>
  <button id="send-manuel" type="button" class="secondary" :disabled="disabled||busy" @click="open('manuel')">Déclarer un envoi manuel…</button>
 </div>
 <div v-if="confirming" id="send-confirm" class="confirm" tabindex="-1" role="group" aria-labelledby="confirm-title">
  <h3 id="confirm-title">{{ confirming==='manuel'?'Confirmer l’envoi manuel':'Confirmer l’envoi par la plateforme' }}</h3>
  <dl><dt>Destinataire</dt><dd>{{ state.destinataire }}</dd><dt>Référence</dt><dd>{{ state.reference }} — version {{ state.version }}</dd></dl>
  <p v-if="confirming==='automatique'">Le PDF sera joint au courriel. Le devis passera en étude côté client et ne sera plus modifiable.</p>
  <template v-else><p>Ne confirmez qu’après avoir envoyé ce PDF depuis votre messagerie : la plateforme n’en a pas la preuve.</p>
   <p class="check"><input id="send-declared" v-model="declared" type="checkbox"><label for="send-declared">J’ai envoyé ce PDF au client depuis ma messagerie.</label></p></template>
  <p class="actions"><button type="button" :disabled="busy" @click="send">{{ busy?'Envoi…':'Confirmer' }}</button><button type="button" class="secondary" :disabled="busy" @click="cancel">Annuler</button></p>
 </div>
 <template v-if="state.transmissions.length"><h3>Historique des envois</h3>
  <ul class="history"><li v-for="t in state.transmissions" :key="t.id">{{ date(t.cree_le) }} — {{ modes[t.mode] }} à {{ t.destinataire }} : <strong>{{ states[t.etat]||t.etat }}</strong><span v-if="t.confirmee_le"> (le {{ date(t.confirmee_le) }})</span></li></ul></template>
</template></section></template>
<style scoped>.actions{display:flex;flex-wrap:wrap;gap:12px}.secondary{background:white;color:#142D43;border:1px solid #142D43}.secondary:hover{background:#E6EEF3}.confirm{border:1px solid #CBD5E1;border-radius:8px;padding:16px;margin:16px 0}dt{font-weight:700}dd{margin:4px 0 12px;overflow-wrap:anywhere}.check{display:flex;gap:12px;align-items:flex-start}.check input{width:24px;min-height:24px;margin-top:4px;flex:none}.check label{margin:0;font-weight:400}.history li,.decisions li{margin-block:8px}.decisions{border-left:4px solid #087F8C;padding-left:12px}</style>
