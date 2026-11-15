<script setup>
// Création et modification d'un événement par l'administratrice (/equipe/evenements/nouveau et /:id/modifier).
import { nextTick, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, request } from './auth'

const route = useRoute(),
  router = useRouter(),
  editing = !!route.params.id

// Le client peut être présélectionné depuis sa fiche (?client=).
const form = reactive({
  client_id: typeof route.query.client === 'string' ? route.query.client : '',
  nom: '',
  debut: '',
  fin: '',
  lieu: '',
  type_evenement: '',
  theme: '',
  participants_estimes: '',
})

const clients = ref([]),
  revision = ref(0),
  loading = ref(true),
  loadError = ref(''),
  errors = ref({}),
  error = ref(''),
  busy = ref(false)

// En modification : charge l'événement et sa révision ; en création : la liste des clients.
onMounted(async () => {
  try {
    if (editing) {
      const data = await request('/api/admin/event-edit?id=' + encodeURIComponent(route.params.id))
      clients.value = data.clients
      const e = data.evenement
      revision.value = e.revision
      if (e.statut === 'annule') loadError.value = 'Cet événement est annulé : il n’est plus modifiable.'
      Object.assign(form, {
        client_id: e.client_id,
        nom: e.nom || '',
        debut: e.debut,
        fin: e.fin,
        lieu: e.lieu || '',
        type_evenement: e.type_evenement || '',
        theme: e.theme || '',
        participants_estimes: e.participants_estimes ?? '',
      })
    } else clients.value = (await request('/api/admin/records-context')).clients
  } catch (e) {
    loadError.value =
      e.status === 403
        ? 'Réservé à l’administratrice.'
        : e.status === 404
          ? 'Cet événement n’existe pas.'
          : 'Le formulaire est indisponible pour le moment.'
  } finally {
    loading.value = false
  }
})

/**
 * Enregistre l'événement puis ouvre sa fiche avec un message de confirmation.
 * La révision envoyée détecte une modification faite entre-temps par quelqu'un d'autre (409).
 */
async function submit() {
  busy.value = true
  errors.value = {}
  error.value = ''
  try {
    const body = editing ? { ...form, id: route.params.id, revision: String(revision.value) } : form
    const data = await request(editing ? '/api/admin/event-update' : '/api/admin/event-new', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify(body),
    })
    await router.push({
      path: '/equipe/evenements/' + (editing ? route.params.id : data.id),
      query: { message: editing ? 'modifie' : 'cree' },
    })
  } catch (e) {
    errors.value = e.fields || {}
    error.value =
      e.status === 422
        ? e.message === 'invalid_client' || !form.client_id
          ? 'Choisissez le client.'
          : 'Vérifiez les champs signalés.'
        : e.message === 'event_revision_conflict'
          ? 'L’événement a été modifié entre-temps. Rechargez la page pour repartir de la dernière version (vos saisies seront perdues).'
          : e.status === 409
            ? 'Cet événement est annulé : il n’est plus modifiable.'
            : e.status === 404
              ? 'Le client ou l’événement n’existe plus.'
              : 'L’enregistrement n’a pas pu être confirmé. Vos saisies sont conservées : réessayez.'
    busy.value = false
    await nextTick()
    const first = Object.keys(errors.value)[0]
    document
      .getElementById(
        first ? 'event-' + first : error.value === 'Choisissez le client.' ? 'event-client_id' : 'event-error',
      )
      ?.focus()
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <main class="panel narrow-form">
    <p>
      <RouterLink :to="editing ? '/equipe/evenements/' + route.params.id : '/equipe/evenements'"
        >← {{ editing ? 'Retour à la fiche' : 'Tous les événements' }}</RouterLink
      >
    </p>
    <h1>{{ editing ? 'Modifier l’événement' : 'Nouvel événement' }}</h1>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="loadError" class="error" role="alert">{{ loadError }}</p>
    <form v-else-if="!loading" novalidate :aria-busy="busy" @submit.prevent="submit">
      <p>Les champs marqués * sont obligatoires. Dates et heures de Paris.</p>
      <p v-if="error" id="event-error" class="error" role="alert" tabindex="-1">{{ error }}</p>
      <fieldset :disabled="busy">
        <legend class="sr-only">Événement</legend>
        <label for="event-client_id">Client *</label>
        <select id="event-client_id" v-model="form.client_id" :disabled="editing" required>
          <option value="">— Choisir un client —</option>
          <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.entreprise }}</option>
        </select>
        <template
          v-for="f in [
            { k: 'nom', l: 'Nom de l’événement *', m: 200 },
            { k: 'lieu', l: 'Lieu', m: 255 },
            { k: 'type_evenement', l: 'Type', m: 100 },
            { k: 'theme', l: 'Thème', m: 100 },
          ]"
          :key="f.k"
        >
          <label :for="'event-' + f.k">{{ f.l }}</label>
          <input
            :id="'event-' + f.k"
            v-model="form[f.k]"
            :maxlength="f.m"
            :aria-invalid="!!errors[f.k]"
            :aria-describedby="errors[f.k] ? 'event-err-' + f.k : undefined"
          />
          <p v-if="errors[f.k]" :id="'event-err-' + f.k" class="error">{{ errors[f.k] }}</p>
        </template>
        <div class="grid">
          <div
            v-for="f in [
              { k: 'debut', l: 'Début' },
              { k: 'fin', l: 'Fin' },
            ]"
            :key="f.k"
          >
            <label :for="'event-' + f.k">{{ f.l }}</label>
            <input
              :id="'event-' + f.k"
              v-model="form[f.k]"
              type="datetime-local"
              :aria-invalid="!!errors[f.k]"
              :aria-describedby="errors[f.k] ? 'event-err-' + f.k : undefined"
            />
            <p v-if="errors[f.k]" :id="'event-err-' + f.k" class="error">{{ errors[f.k] }}</p>
          </div>
        </div>
        <label for="event-participants_estimes">Participants estimés</label>
        <input
          id="event-participants_estimes"
          v-model="form.participants_estimes"
          inputmode="numeric"
          maxlength="10"
          :aria-invalid="!!errors.participants_estimes"
          :aria-describedby="errors.participants_estimes ? 'event-err-participants_estimes' : undefined"
        />
        <p v-if="errors.participants_estimes" id="event-err-participants_estimes" class="error">
          {{ errors.participants_estimes }}
        </p>
        <button type="submit">
          {{ busy ? 'Enregistrement…' : editing ? 'Enregistrer les modifications' : 'Créer l’événement (brouillon)' }}
        </button>
      </fieldset>
    </form>
  </main>
</template>
<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 0 16px;
}
form button[type='submit'] {
  margin-top: 24px;
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}
</style>
