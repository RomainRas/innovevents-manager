<script setup>
// Écran de préparation du devis d'un événement (administratrice) : prestations, taux de TVA et totaux,
// puis génération du PDF (QuotePdf) et transmission au client (QuoteTransmission).
import { computed, ref, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { request, auth } from './auth'
import QuotePdf from './QuotePdf.vue'
import QuoteTransmission from './QuoteTransmission.vue'
import { eventStatuses } from './teamFormat'

// Verrous posés par les blocs PDF et transmission pendant leurs opérations (le formulaire est alors bloqué).
const pdfLocked = ref(false), sendLocked = ref(false), pdfKey = ref(0)
const route = useRoute(), loading = ref(true), busy = ref(false), uncertain = ref(false)
const error = ref(''), errors = ref({}), notice = ref('')
// Libellé du statut de l'événement, pour expliquer pourquoi aucun devis ne peut être préparé.
const statusLabel = computed(() => eventStatuses[event.value?.statut] || event.value?.statut || '')
const event = ref(null), revision = ref(0), editable = ref(true), reference = ref(''), rate = ref('')
const lines = ref([{ libelle: '', montant_ht: '' }]), totals = ref(null)

// Enregistrement en cours, gardé à l'identique tant que son résultat est inconnu.
let pending = null
// Change de valeur pour recréer le bloc de transmission après une contre-proposition.
const counterKey = ref(0)

/** Charge l'événement et le devis courant ; le formulaire n'est modifiable que pour un devis et un événement brouillons. */
async function load() {
  loading.value = true; error.value = ''
  try {
    const d = await request('/api/admin/quote-draft?event_id=' + route.params.id)
    event.value = d.event
    if (d.quote) {
      const q = d.quote
      revision.value = q.revision
      reference.value = q.reference
      rate.value = q.taux_tva ?? ''
      lines.value = q.prestations.length ? q.prestations : [{ libelle: '', montant_ht: '' }]
      editable.value = q.editable
      totals.value = q.total_ht === null ? null : q
    }
    editable.value = editable.value && d.event.statut === 'brouillon'
  } catch {
    error.value = 'Impossible de consulter ce devis. Accès réservé à l’administratrice.'
  } finally {
    loading.value = false
  }
}
onMounted(load)

/** Nouvelle version créée par une contre-proposition : rechargement et message d'aide. */
async function counterCreated() {
  editable.value = true
  await load()
  counterKey.value++
  notice.value = 'Nouvelle version créée à partir de la précédente : modifiez les prestations, enregistrez, générez le PDF puis transmettez-la.'
  await nextTick(); document.getElementById('quote-result')?.focus()
}

/** Une saisie a changé : les totaux affichés ne sont plus à jour tant qu'on n'a pas enregistré. */
function changed() {
  totals.value = null
  notice.value = ''
}

/** Enregistre les prestations ; le serveur recalcule les totaux (ils ne sont jamais calculés dans le navigateur). */
async function save() {
  busy.value = true; error.value = ''; errors.value = {}; notice.value = ''
  try {
    pending ??= JSON.stringify({ event_id: route.params.id, revision: revision.value, taux_tva: rate.value, prestations: lines.value })
    const d = await request('/api/admin/quote-draft-save', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf }, body: pending })
    revision.value = d.revision
    reference.value = d.reference
    totals.value = d
    lines.value = d.prestations
    rate.value = d.taux_tva
    pending = null; uncertain.value = false
    notice.value = 'Prestations et totaux enregistrés.'
    await nextTick(); document.getElementById('quote-result')?.focus()
  } catch (e) {
    errors.value = e.fields || {}
    if (e.status === 422) {
      error.value = 'Vérifiez les champs signalés.'
      pending = null; uncertain.value = false
    } else if (e.status === 409) {
      error.value = 'Le devis ou l’événement a changé. Rechargez la page avant de continuer.'
      pending = null; uncertain.value = false
    } else if (e.status === 401 || e.status === 403) {
      error.value = 'Votre session ou vos droits ne permettent plus cette action.'
      pending = null; uncertain.value = false
    } else {
      // Réponse perdue : le même enregistrement sera renvoyé, sans risque de doublon.
      error.value = 'Enregistrement non confirmé. Réessayez avec les mêmes prestations.'
      uncertain.value = true
    }
    // Réactiver le formulaire avant d'y placer le focus (premier champ en erreur, sinon le message).
    busy.value = false
    await nextTick()
    ;(document.getElementById(Object.keys(errors.value)[0]) || document.getElementById('quote-error'))?.focus()
  } finally {
    busy.value = false
  }
}
</script>
<template><main class="poster-page quote-poster"><span class="shape red torn-a deco-a" aria-hidden="true"></span><span class="shape yellow torn-b deco-b" aria-hidden="true"></span><span class="shape yellow torn-a deco-c" aria-hidden="true"></span><span class="shape blue torn-b deco-d" aria-hidden="true"></span><div class="paper sheet"><RouterLink class="back-link" :to="'/evenements/'+route.params.id">← Retour à l’événement</RouterLink><h1 class="poster-title yellow">Préparer le devis</h1><p v-if="loading" role="status">Chargement…</p><p v-if="error" id="quote-error" role="alert" tabindex="-1" class="error">{{ error }}</p><p v-if="notice" id="quote-result" role="status" tabindex="-1">{{ notice }}</p>
<template v-if="event"><p class="quote-event">{{ event.nom }} <span v-if="reference" class="status-pill">{{ reference }}</span></p><p class="tva-info">Montants en euros. La TVA est calculée sur le total HT, puis arrondie au centime. Renseignez le taux applicable : aucun taux n’est présélectionné.</p><p v-if="!editable&&!reference" class="notice">Aucun devis n’a été préparé pour cet événement. Le devis se prépare tant que l’événement est en brouillon ; celui-ci est au statut « {{ statusLabel }} ».</p><p v-else-if="!editable">Ce devis n’est plus modifiable dans cet écran.</p>
<form v-if="editable||reference" class="quote-form" @submit.prevent="save" novalidate :aria-busy="busy"><fieldset :disabled="busy||uncertain||pdfLocked||sendLocked||!editable"><legend>Prestations</legend><p v-if="errors.prestations" id="prestations" tabindex="-1" class="error">{{ errors.prestations }}</p>
<div v-for="(line,i) in lines" :key="i" class="line"><label :for="'libelle-'+i">Prestation {{ i+1 }} — libellé</label><input :id="'libelle-'+i" v-model="line.libelle" maxlength="255" required :aria-invalid="!!errors['libelle-'+i]" :aria-describedby="errors['libelle-'+i]?'libelle-error-'+i:undefined" @input="changed"><p v-if="errors['libelle-'+i]" :id="'libelle-error-'+i" class="error">{{ errors['libelle-'+i] }}</p>
<label :for="'montant-'+i">Montant HT {{ i+1 }} (€)</label><input :id="'montant-'+i" v-model="line.montant_ht" inputmode="decimal" required :aria-invalid="!!errors['montant-'+i]" :aria-describedby="errors['montant-'+i]?'montant-error-'+i:undefined" @input="changed"><p v-if="errors['montant-'+i]" :id="'montant-error-'+i" class="error">{{ errors['montant-'+i] }}</p><button type="button" class="remove" :disabled="lines.length===1" :aria-label="'Supprimer la prestation '+(i+1)" @click="lines.splice(i,1);changed()">Supprimer</button></div>
<p class="add-line"><button type="button" class="secondary" :disabled="lines.length>=100" @click="lines.push({libelle:'',montant_ht:''});changed()">Ajouter une prestation</button></p><label for="taux_tva">Taux de TVA (%)</label><input id="taux_tva" v-model="rate" inputmode="decimal" required :aria-invalid="!!errors.taux_tva" :aria-describedby="errors.taux_tva?'taux-error':undefined" @input="changed"><p v-if="errors.taux_tva" id="taux-error" class="error">{{ errors.taux_tva }}</p></fieldset>
<p class="save-quote"><button :disabled="busy||pdfLocked||sendLocked||!editable">{{ busy?'Enregistrement…':uncertain?'Vérifier et réessayer':'Calculer et enregistrer' }}</button></p></form>
<div class="results"><section v-if="totals" class="totals" aria-labelledby="totals-title"><h2 id="totals-title" class="hl">Totaux enregistrés</h2><dl><dt>Total HT</dt><dd>{{ totals.total_ht }} €</dd><dt>TVA</dt><dd>{{ totals.total_tva }} €</dd><dt>Total TTC</dt><dd>{{ totals.total_ttc }} €</dd></dl></section><p v-else-if="editable||reference">Les totaux seront affichés après validation et enregistrement.</p>
<QuotePdf v-if="totals && revision>0 && editable" :key="revision" :event-id="String(route.params.id)" :revision="revision" :disabled="busy||uncertain||sendLocked||!editable" @locked="pdfLocked=$event" @generated="pdfKey++" /></div>
<QuoteTransmission v-if="totals && revision>0" :key="revision+'-'+pdfKey+'-'+counterKey" :event-id="String(route.params.id)" :disabled="busy||uncertain||pdfLocked" @locked="sendLocked=$event" @sent="editable=false" @counter="counterCreated" /></template></div></main></template>
<style scoped>
/* Préparation du devis en style affiche : feuille déchirée sur aplats, titres surlignés de jaune. */
.sheet {
  position: relative;
  z-index: 1;
  max-width: 1000px;
  margin: 0 auto;
  font-family: Georgia, 'Times New Roman', serif;
}
.sheet .back-link {
  font-family: Georgia, 'Times New Roman', serif;
  font-weight: 700;
  color: #1d3fbf;
}
.sheet .poster-title {
  display: block;
  width: fit-content;
  margin: 8px auto 24px;
  font-size: clamp(40px, 6vw, 68px);
}
.quote-event {
  margin: 0 0 6px;
  font-weight: 700;
}
.quote-event .status-pill {
  font-family: system-ui, sans-serif;
  font-size: 0.9rem;
}
.tva-info {
  margin: 0 0 18px;
}
.quote-form {
  margin: 0;
}
fieldset {
  min-width: 0;
  margin: 0 0 18px;
  padding: 10px 24px 18px;
  border: 2px solid #111;
  border-radius: 6px;
}
legend {
  padding: 0 8px;
  font-weight: 700;
  font-size: 1.25rem;
}
.line {
  display: grid;
  grid-template-columns: minmax(0, 2fr) minmax(140px, 1fr) auto;
  gap: 4px 16px;
  align-items: end;
  padding: 8px 0;
}
.line label[for^='libelle'] {
  grid-area: 1 / 1;
}
.line input[id^='libelle'] {
  grid-area: 2 / 1;
}
.line label[for^='montant'] {
  grid-area: 1 / 2;
}
.line input[id^='montant'] {
  grid-area: 2 / 2;
  text-align: right;
}
.line [id^='libelle-error'] {
  grid-area: 3 / 1;
}
.line [id^='montant-error'] {
  grid-area: 3 / 2;
}
.line label {
  margin: 0;
}
.line .error {
  margin: 0;
}
.remove {
  grid-area: 2 / 3;
  min-height: 44px;
  color: #111 !important;
  background: #efe8d8 !important;
  border: 1px solid #111 !important;
  border-radius: 4px;
  font-family: Georgia, 'Times New Roman', serif;
}
.add-line .secondary {
  color: #111 !important;
  background: #ffd836 !important;
  border: 0 !important;
  border-radius: 0;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-size: 1.15rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}
#taux_tva {
  max-width: 260px;
}
.save-quote {
  display: flex;
  justify-content: flex-end;
}
.save-quote button,
.sheet :deep(section button:not(.secondary)) {
  border-radius: 0;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-size: 1.35rem;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}
/* Totaux à gauche, PDF à droite, séparés par un filet ; transmission en dessous. */
.results {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.3fr);
  gap: 24px 40px;
  margin-top: 24px;
  padding-top: 24px;
  border-top: 2px solid #111;
}
.results > :nth-child(2) {
  padding-left: 32px;
  border-left: 2px solid #111;
}
.results > :only-child {
  grid-column: 1 / -1;
}
.totals dl {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px 24px;
  margin: 0;
}
.totals dt {
  font-weight: 700;
}
.totals dd {
  margin: 0;
  text-align: right;
  font-variant-numeric: tabular-nums;
}
.sheet :deep(.hl) {
  width: fit-content;
  margin: 0 0 14px;
  padding: 0 8px;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-weight: 400;
  font-size: clamp(28px, 3.4vw, 40px);
  line-height: 1.1;
  text-transform: uppercase;
  color: #111;
  background: linear-gradient(transparent 55%, #ffd836 55%);
}
.sheet :deep(section[aria-labelledby='send-title']) {
  margin-top: 24px;
  padding-top: 24px;
  border-top: 2px solid #111;
}
[aria-invalid='true'] {
  border: 2px solid #b42318;
}
.deco-a {
  top: -20px;
  left: -60px;
  width: 520px;
  height: 380px;
  transform: rotate(-6deg);
}
.deco-b {
  bottom: 40px;
  left: -80px;
  width: 380px;
  height: 180px;
  transform: rotate(8deg);
}
.deco-c {
  top: 460px;
  right: -60px;
  width: 360px;
  height: 140px;
  transform: rotate(-24deg);
}
.deco-d {
  bottom: 20px;
  right: -60px;
  width: 520px;
  height: 360px;
  transform: rotate(-14deg);
}
@media (max-width: 800px) {
  .results {
    grid-template-columns: minmax(0, 1fr);
  }
  .results > :nth-child(2) {
    padding-left: 0;
    border-left: 0;
  }
}
@media (max-width: 700px) {
  fieldset {
    padding: 8px 14px 14px;
  }
  .line {
    grid-template-columns: minmax(0, 1fr);
  }
  .line > * {
    grid-area: auto !important;
  }
  .remove {
    justify-self: start;
  }
}
</style>
