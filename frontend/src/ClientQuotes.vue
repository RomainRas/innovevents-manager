<script setup>
// Bloc « Vos devis » de l'espace client : consultation des devis transmis, téléchargement du PDF
// et réponse (accepter, refuser ou demander une modification avec un motif).
import { ref, onMounted, nextTick } from 'vue'
import { auth, request } from './auth'

const quotes = ref([]), loading = ref(true), error = ref(''), notice = ref(''), busy = ref('')
const dialog = ref(null), reason = ref(''), reasonError = ref(''), uncertain = ref(false)

// Réponse en cours (gardée tant que son résultat est inconnu) et bouton qui a ouvert la confirmation (retour du focus).
let pending = null, origin = null

// Libellés affichés selon le statut ou le choix.
const labels = { etude_client: 'À étudier', modification: 'Modification demandée', accepte: 'Accepté', refuse: 'Refusé' }
const titles = { accepte: 'Accepter ce devis', refuse: 'Refuser ce devis', modification: 'Demander une modification' }
const confirms = { accepte: 'Confirmer l’acceptation', refuse: 'Confirmer le refus', modification: 'Envoyer la demande' }
const results = {
  accepte: 'Devis accepté. Innov’Events est informée et prépare l’organisation de votre événement.',
  refuse: 'Refus enregistré. Votre devis reste consultable dans votre espace.',
  modification: 'Demande de modification enregistrée. Innov’Events vous proposera une nouvelle version.',
}

/** Montant en euros, format français (1 234,56 €). */
const money = v => Number(v).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })
/** Date locale « AAAA-MM-JJTHH:MM » → « JJ/MM/AAAA à HH h MM ». */
const local = v => {
  const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(v || '')
  return m ? `${m[3]}/${m[2]}/${m[1]} à ${m[4]} h ${m[5]}` : ''
}
/** Horodatage UTC de la base → date de Paris. */
const day = v => v ? new Date(v.replace(' ', 'T').slice(0, 19) + 'Z').toLocaleDateString('fr-FR', { timeZone: 'Europe/Paris' }) : ''

async function load() {
  try { quotes.value = (await request('/api/me/quotes')).devis }
  catch { error.value = 'Vos devis sont indisponibles pour le moment.' }
  finally { loading.value = false }
}
onMounted(load)

/** Ouvre la confirmation d'une réponse ; le focus va au motif (modification) ou au panneau. */
async function open(q, choice, event) {
  origin = event.currentTarget
  dialog.value = { q, choice }
  reason.value = ''; reasonError.value = ''; error.value = ''; notice.value = ''
  await nextTick()
  document.getElementById(choice === 'modification' ? 'decision-reason' : 'decision-dialog')?.focus()
}
function cancel() {
  dialog.value = null
  origin?.focus()
}

/** Envoie la réponse confirmée (ou la renvoie à l'identique si le résultat précédent est inconnu). */
async function decide() {
  const { q, choice } = pending && uncertain.value ? pending : dialog.value
  if (choice === 'modification' && !uncertain.value && !reason.value.trim()) {
    reasonError.value = 'Indiquez le motif de votre demande de modification.'
    await nextTick(); document.getElementById('decision-reason')?.focus()
    return
  }

  // Même identifiant d'opération tant que le résultat est inconnu : aucune double réponse.
  if (!pending) {
    pending = { q, choice, body: JSON.stringify({ version_id: q.version_id, choix: choice, motif: choice === 'modification' ? reason.value : undefined, cle: crypto.randomUUID() }) }
  }
  busy.value = q.version_id; error.value = ''; reasonError.value = ''

  try {
    await request('/api/me/quote-decision', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf }, body: pending.body })
    const done = results[pending.choice]
    pending = null; uncertain.value = false; dialog.value = null
    await load()
    notice.value = done
    await nextTick(); document.getElementById('quotes-result')?.focus()
  } catch (e) {
    if (e.status === 422) {
      // Motif manquant ou trop long : affiché sous le champ.
      reasonError.value = e.fields?.motif || ''
      error.value = e.fields?.motif ? '' : 'Réponse incomplète.'
      pending = null; uncertain.value = false
    } else if (e.status === 409 || e.status === 404) {
      error.value = e.message === 'quote_version_replaced'
        ? 'Une nouvelle version de ce devis vous a été proposée. Consultez-la avant de répondre.'
        : 'Ce devis a déjà reçu une réponse ou n’est plus disponible.'
      pending = null; uncertain.value = false; dialog.value = null
      await load()
    } else if (e.status === 401 || e.status === 403) {
      error.value = 'Votre session a expiré. Reconnectez-vous pour répondre.'
      pending = null; uncertain.value = false
    } else {
      // Réponse perdue : on garde l'opération pour la revérifier sans risque de double réponse.
      error.value = 'Résultat inconnu : votre réponse a peut-être été enregistrée. Vérifiez avant de répondre à nouveau.'
      uncertain.value = true
    }
    await nextTick()
    ;(reasonError.value ? document.getElementById('decision-reason') : document.getElementById('quotes-error'))?.focus()
  } finally {
    busy.value = ''
  }
}

/** Télécharge le PDF du devis (fichier créé dans le navigateur à partir de la réponse). */
async function download(q) {
  busy.value = 'pdf-' + q.document_id; error.value = ''
  try {
    const r = await fetch('/api/me/quote-pdf?id=' + q.document_id, { credentials: 'same-origin', signal: AbortSignal.timeout(15000) })
    if (!r.ok) throw Error()
    const url = URL.createObjectURL(await r.blob()), a = document.createElement('a')
    a.href = url
    a.download = `devis-${q.reference}-v${q.version}.pdf`
    a.click()
    setTimeout(() => URL.revokeObjectURL(url), 60000)
  } catch {
    error.value = 'Téléchargement indisponible. Vérifiez votre connexion et réessayez.'
    await nextTick(); document.getElementById('quotes-error')?.focus()
  } finally {
    busy.value = ''
  }
}
</script>
<template><section aria-labelledby="quotes-title"><h2 id="quotes-title">Vos devis</h2>
<p v-if="loading" role="status">Chargement…</p><p v-if="error" id="quotes-error" tabindex="-1" role="alert" class="error">{{ error }}</p>
<p v-if="notice" id="quotes-result" tabindex="-1" role="status" class="success">{{ notice }}</p>
<p v-if="uncertain"><button type="button" :disabled="!!busy" @click="decide">{{ busy?'Vérification…':'Vérifier ma réponse' }}</button></p>
<p v-if="!loading&&!error&&!quotes.length">Aucun devis ne vous a encore été transmis.</p>
<article v-for="q in quotes" :key="q.version_id" class="quote" :class="{old:q.remplace}" :aria-labelledby="'quote-'+q.version_id">
 <h3 :id="'quote-'+q.version_id">{{ q.evenement.nom }}</h3>
 <p>Devis {{ q.reference }}, version {{ q.version }} — transmis le {{ day(q.transmis_le) }}<br><strong>Statut : {{ labels[q.statut]||q.statut }}</strong><span v-if="q.remplace"> — remplacé par une version plus récente</span></p>
 <p v-if="q.decision">Votre réponse du {{ day(q.decision.decidee_le) }} : {{ labels[q.decision.choix] }}<template v-if="q.decision.motif"><br>Motif : « {{ q.decision.motif }} »</template></p>
 <p>{{ q.evenement.lieu }}<br>Du {{ local(q.evenement.debut) }} au {{ local(q.evenement.fin) }} (heure de Paris)</p>
 <table><caption>Prestations</caption><thead><tr><th scope="col">Prestation</th><th scope="col">Montant HT</th></tr></thead>
  <tbody><tr v-for="(l,i) in q.prestations" :key="i"><td>{{ l.libelle }}</td><td>{{ money(l.montant_ht) }}</td></tr></tbody>
  <tfoot><tr><th scope="row">Total HT</th><td>{{ money(q.total_ht) }}</td></tr><tr><th scope="row">TVA ({{ Number(q.taux_tva).toLocaleString('fr-FR') }} %)</th><td>{{ money(q.total_tva) }}</td></tr><tr><th scope="row">Total TTC</th><td><strong>{{ money(q.total_ttc) }}</strong></td></tr></tfoot></table>
 <p v-if="q.document_id"><button type="button" class="secondary" :disabled="!!busy" @click="download(q)">{{ busy==='pdf-'+q.document_id?'Téléchargement…':'Télécharger le PDF' }}</button></p>
 <div v-if="q.decidable&&!uncertain&&dialog?.q.version_id!==q.version_id" class="actions" role="group" :aria-label="'Répondre au devis '+q.reference">
  <button type="button" :disabled="!!busy" @click="open(q,'accepte',$event)">Accepter le devis…</button>
  <button type="button" class="secondary" :disabled="!!busy" @click="open(q,'modification',$event)">Demander une modification…</button>
  <button type="button" class="secondary" :disabled="!!busy" @click="open(q,'refuse',$event)">Refuser le devis…</button>
 </div>
 <div v-if="dialog?.q.version_id===q.version_id&&!uncertain" id="decision-dialog" class="dialog" tabindex="-1" role="group" aria-labelledby="decision-title">
  <h4 id="decision-title">{{ titles[dialog.choice] }}</h4>
  <p>Devis {{ q.reference }}, version {{ q.version }} — {{ q.evenement.nom }} — total TTC {{ money(q.total_ttc) }}.</p>
  <p v-if="dialog.choice==='accepte'">Votre acceptation sera transmise à Innov’Events. Elle ne pourra pas être modifiée en ligne.</p>
  <p v-if="dialog.choice==='refuse'">Ce devis sera marqué comme refusé. Il restera consultable dans votre espace.</p>
  <template v-if="dialog.choice==='modification'"><label for="decision-reason">Motif de la modification (obligatoire)</label>
   <textarea id="decision-reason" v-model="reason" rows="4" maxlength="2000" required :aria-invalid="!!reasonError" :aria-describedby="reasonError?'decision-reason-error':'decision-reason-help'"></textarea>
   <p v-if="reasonError" id="decision-reason-error" class="error">{{ reasonError }}</p><p v-else id="decision-reason-help" class="help">Précisez ce que vous souhaitez voir évoluer : prestations, quantités, dates…</p></template>
  <p class="actions"><button type="button" :disabled="!!busy" @click="decide">{{ busy?'Enregistrement…':confirms[dialog.choice] }}</button><button type="button" class="secondary" :disabled="!!busy" @click="cancel">Annuler</button></p>
 </div>
</article></section></template>
<style scoped>.quote{border:1px solid #CBD5E1;border-radius:8px;padding:16px;margin:16px 0}.quote.old{opacity:.8}h3{margin-top:0;overflow-wrap:anywhere}table{width:100%;border-collapse:collapse;margin:16px 0}caption{text-align:left;font-weight:700;margin-bottom:8px}th,td{text-align:left;padding:8px 4px;border-bottom:1px solid #E2E8F0;overflow-wrap:anywhere}td:last-child{text-align:right;white-space:nowrap}tfoot th{font-weight:600}.actions{display:flex;flex-wrap:wrap;gap:12px}.secondary{background:white;color:#142D43;border:1px solid #142D43}.secondary:hover{background:#E6EEF3}.dialog{border:2px solid #087F8C;border-radius:8px;padding:16px;margin-top:16px}h4{margin:0 0 8px;font-size:1.1rem}textarea{width:100%;padding:12px;border:1px solid #718391;border-radius:4px;font:inherit}[aria-invalid=true]{border:2px solid #B42318}.help{color:#4A5B68;font-size:.95rem}.success{color:#0B6B3A;font-weight:600}p{overflow-wrap:anywhere}</style>
