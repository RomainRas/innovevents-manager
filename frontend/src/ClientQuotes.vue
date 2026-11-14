<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { request } from './auth'
const quotes=ref([]),loading=ref(true),error=ref(''),busy=ref('')
const labels={etude_client:'À étudier',modification:'Modification demandée',accepte:'Accepté',refuse:'Refusé'}
const money=v=>Number(v).toLocaleString('fr-FR',{style:'currency',currency:'EUR'})
const local=v=>{const m=/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(v||'');return m?`${m[3]}/${m[2]}/${m[1]} à ${m[4]} h ${m[5]}`:''}
const sentOn=v=>v?new Date(v.replace(' ','T').slice(0,19)+'Z').toLocaleDateString('fr-FR',{timeZone:'Europe/Paris'}):''
onMounted(async()=>{try{quotes.value=(await request('/api/me/quotes')).devis}catch{error.value='Vos devis sont indisponibles pour le moment.'}finally{loading.value=false}})
async function download(q){busy.value=q.document_id;error.value=''
 try{const r=await fetch('/api/me/quote-pdf?id='+q.document_id,{credentials:'same-origin',signal:AbortSignal.timeout(15000)});if(!r.ok)throw Error()
  const url=URL.createObjectURL(await r.blob()),a=document.createElement('a');a.href=url;a.download=`devis-${q.reference}-v${q.version}.pdf`;a.click();setTimeout(()=>URL.revokeObjectURL(url),60000)}
 catch{error.value='Téléchargement indisponible. Vérifiez votre connexion et réessayez.';await nextTick();document.getElementById('quotes-error')?.focus()}
 finally{busy.value=''}}
</script>
<template><section aria-labelledby="quotes-title"><h2 id="quotes-title">Vos devis</h2>
<p v-if="loading" role="status">Chargement…</p><p v-if="error" id="quotes-error" tabindex="-1" role="alert" class="error">{{ error }}</p>
<p v-if="!loading&&!error&&!quotes.length">Aucun devis ne vous a encore été transmis.</p>
<article v-for="q in quotes" :key="q.reference+'-'+q.version" class="quote" :aria-labelledby="'quote-'+q.reference">
 <h3 :id="'quote-'+q.reference">{{ q.evenement.nom }}</h3>
 <p>Devis {{ q.reference }}, version {{ q.version }} — transmis le {{ sentOn(q.transmis_le) }}<br><strong>Statut : {{ labels[q.statut]||q.statut }}</strong></p>
 <p>{{ q.evenement.lieu }}<br>Du {{ local(q.evenement.debut) }} au {{ local(q.evenement.fin) }} (heure de Paris)</p>
 <table><caption>Prestations</caption><thead><tr><th scope="col">Prestation</th><th scope="col">Montant HT</th></tr></thead>
  <tbody><tr v-for="(l,i) in q.prestations" :key="i"><td>{{ l.libelle }}</td><td>{{ money(l.montant_ht) }}</td></tr></tbody>
  <tfoot><tr><th scope="row">Total HT</th><td>{{ money(q.total_ht) }}</td></tr><tr><th scope="row">TVA ({{ Number(q.taux_tva).toLocaleString('fr-FR') }} %)</th><td>{{ money(q.total_tva) }}</td></tr><tr><th scope="row">Total TTC</th><td><strong>{{ money(q.total_ttc) }}</strong></td></tr></tfoot></table>
 <p v-if="q.document_id"><button type="button" :disabled="!!busy" @click="download(q)">{{ busy===q.document_id?'Téléchargement…':'Télécharger le PDF' }}</button></p>
</article></section></template>
<style scoped>.quote{border:1px solid #CBD5E1;border-radius:8px;padding:16px;margin:16px 0}h3{margin-top:0;overflow-wrap:anywhere}table{width:100%;border-collapse:collapse;margin:16px 0}caption{text-align:left;font-weight:700;margin-bottom:8px}th,td{text-align:left;padding:8px 4px;border-bottom:1px solid #E2E8F0;overflow-wrap:anywhere}td:last-child{text-align:right;white-space:nowrap}tfoot th{font-weight:600}</style>
