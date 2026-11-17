<script setup>
// Brouillon d'événement (administratrice) : création depuis une demande convertie (/prospects/:id/evenement)
// ou modification d'un brouillon existant (/evenements/:id), avec l'image de l'événement (EventImage).
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, request, refresh } from './auth'
import EventImage from './EventImage.vue'

const imageLocked = ref(false), hasImage = ref(false), imageAlt = ref('')
const route = useRoute(), router = useRouter()
const creating = computed(() => route.meta.eventMode === 'create')

const form = reactive({ nom: '', lieu: '', type_evenement: '', participants_estimes: '', debut: '', fin: '' })
// Champs du formulaire : [clé, libellé, type de champ, longueur maximale].
const fields = [
  ['nom', 'Nom de l’événement', 'text', 200],
  ['lieu', 'Lieu', 'text', 255],
  ['type_evenement', 'Type d’événement', 'text', 100],
  ['participants_estimes', 'Participants estimés', 'number', 10],
  ['debut', 'Date et heure de début', 'datetime-local', 30],
  ['fin', 'Date et heure de fin', 'datetime-local', 30],
]

const loading = ref(true), busy = ref(false), error = ref(''), errors = ref({}), notice = ref(''), uncertain = ref(false)
const revision = ref(1), client = ref(null), status = ref('brouillon'), requested = ref('')

onMounted(async () => {
  try {
    if (creating.value) {
      // Création : on propose les informations de la demande ; si l'événement existe déjà, on l'ouvre.
      const d = await request('/api/admin/event-context?' + new URLSearchParams({ prospect_id: route.params.id }))
      client.value = d.client_id
      if (d.event_id) {
        await router.replace('/evenements/' + d.event_id)
        return
      }
      const s = d.suggested
      Object.assign(form, { nom: s.nom, lieu: s.lieu, type_evenement: s.type_evenement, participants_estimes: s.participants_estimes })
      requested.value = s.date_souhaitee
    } else {
      const e = (await request('/api/admin/event?' + new URLSearchParams({ id: route.params.id }))).event
      client.value = e.client_id
      revision.value = e.revision
      status.value = e.statut
      hasImage.value = e.has_image
      imageAlt.value = e.image_alternative || ''
      for (const [key] of fields) form[key] = e[key] ?? ''
    }
  } catch (e) {
    error.value = e.status === 403 ? 'Accès réservé à l’administratrice.' : 'Impossible de charger cet événement ou cette demande.'
  } finally {
    loading.value = false
  }
})

/** Crée l'événement (puis ouvre sa fiche) ou enregistre le brouillon avec son numéro de révision. */
async function submit() {
  busy.value = true; error.value = ''; errors.value = {}; notice.value = ''
  try {
    const payload = creating.value
      ? { prospect_id: route.params.id, ...form }
      : { id: route.params.id, revision: revision.value, ...form }
    const d = await request(creating.value ? '/api/admin/event-create' : '/api/admin/event-save', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify(payload),
    })
    uncertain.value = false
    if (creating.value) {
      await router.replace({ path: '/evenements/' + d.event_id, query: { cree: '1' } })
      return
    }
    revision.value = d.revision
    notice.value = 'Brouillon enregistré.'
    await nextTick(); document.getElementById('event-result')?.focus()
  } catch (e) {
    errors.value = e.fields || {}
    if (e.status === 422) {
      error.value = 'Vérifiez les champs signalés.'
    } else if (e.status === 409) {
      error.value = 'Cet événement existe déjà ou a changé depuis votre ouverture. Rechargez sa fiche avant de continuer ; vos saisies actuelles seront alors remplacées.'
    } else if (e.status === 401 || e.status === 403) {
      error.value = 'Votre session ou vos droits ne permettent plus cette action.'
      try { await refresh() } catch { /* Garder la saisie. */ }
    } else {
      uncertain.value = true
      error.value = 'Nous n’avons pas pu confirmer l’enregistrement. Réessayez avec les mêmes informations.'
    }
    busy.value = false
    await nextTick()
    ;(document.getElementById(Object.keys(errors.value)[0]) || document.getElementById('event-error'))?.focus()
  } finally {
    busy.value = false
  }
}
</script>
<template><main class="work-page draft-page"><RouterLink v-if="client" class="back-link" :to="'/clients/'+client">← Retour au client</RouterLink><h1 class="draft-title">{{ creating?'Préparer l’événement':'Événement — '+(form.nom||'fiche') }}</h1>
 <p v-if="loading" role="status">Chargement…</p><p v-if="error" id="event-error" role="alert" tabindex="-1" class="error">{{ error }}</p>
 <p v-if="route.query.cree==='1'" role="status">Événement créé en brouillon.</p><p v-if="notice" id="event-result" role="status" tabindex="-1">{{ notice }}</p>
 <template v-if="!loading && client"><p class="draft-status">Statut : <strong class="status-pill">{{ status==='brouillon'?'Brouillon':status }}</strong> Ce formulaire ne publie pas l’événement.</p><p v-if="requested">Date souhaitée dans la demande : {{ requested.split('-').reverse().join('/') }}. Les heures restent à préciser.</p>
 <p>Heures de Paris (Europe/Paris). Le nom est obligatoire ; les autres informations peuvent être complétées plus tard dans ce brouillon.</p>
 <form class="work-card draft-form" @submit.prevent="submit" novalidate :aria-busy="busy"><div class="event-grid"><div v-for="[id,label,type,max] in fields" :key="id"><label :for="id">{{ label }}{{ id==='nom'?' *':'' }}</label><input :id="id" v-model="form[id]" :type="type" :maxlength="max" :required="id==='nom'" :min="type==='number'?1:undefined" :disabled="busy || uncertain || imageLocked || status!=='brouillon'" :aria-invalid="!!errors[id]" :aria-describedby="errors[id]?id+'-error':undefined"><p v-if="errors[id]" :id="id+'-error'" class="error">{{ errors[id] }}</p></div></div><p class="draft-actions"><button class="secondary save-draft" :disabled="busy || imageLocked || status!=='brouillon'">{{ busy?'Enregistrement…':uncertain?'Vérifier et réessayer':creating?'Créer le brouillon':'Enregistrer le brouillon' }}</button></p></form><p v-if="!creating" class="to-quote"><RouterLink class="quote-link" :to="'/evenements/'+route.params.id+'/devis'">Préparer les prestations et le devis <span aria-hidden="true">→</span></RouterLink></p><EventImage v-if="!creating" :id="String(route.params.id)" :revision="revision" :alternative="imageAlt" :has-image="hasImage" :disabled="busy||uncertain||status!=='brouillon'" @locked="imageLocked=$event" @saved="revision=$event.revision;imageAlt=$event.alternative;hasImage=true" /></template>
 </main></template>
<style scoped>
/* Préparation de l'événement : titre souligné, statut en pastille, formulaire en carte sur deux colonnes. */
.draft-title {
  margin: 8px 0 16px;
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: clamp(34px, 4.6vw, 54px);
  line-height: 1.1;
  color: var(--encre);
  overflow-wrap: anywhere;
}
.draft-title::after {
  content: '';
  display: block;
  width: 90px;
  height: 6px;
  margin-top: 10px;
  border-radius: 3px;
  background: #d4a600;
}
.draft-status {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 8px 12px;
}
.draft-form {
  margin: 20px 0;
}
.event-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 28px;
}
.event-grid label {
  margin-top: 16px;
}
.draft-actions {
  display: flex;
  justify-content: flex-end;
  margin: 24px 0 0;
}
.save-draft {
  border: 2px solid var(--encre) !important;
  border-radius: 6px;
  font-weight: 700;
}
.to-quote {
  display: flex;
  justify-content: flex-end;
  margin: 0 0 8px;
}
.quote-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 48px;
  padding: 12px 24px;
  color: white;
  background: var(--rouge);
  border-radius: 6px;
  font-weight: 700;
  text-decoration: none;
}
.quote-link:hover {
  background: var(--rouge-fonce);
}
@media (max-width: 600px) {
  .event-grid {
    grid-template-columns: 1fr;
  }
  .draft-actions,
  .to-quote {
    justify-content: stretch;
  }
  .save-draft,
  .quote-link {
    width: 100%;
    justify-content: center;
  }
}
</style>
