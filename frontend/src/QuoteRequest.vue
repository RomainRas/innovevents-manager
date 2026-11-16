<script setup>
// Formulaire public de demande de devis. La demande est enregistrée comme prospect et l'agence est prévenue par courriel.
import { nextTick, reactive, ref } from 'vue'
import { auth, refresh, request } from './auth'

// Champs regroupés par rubrique : [nom, libellé, type de saisie, longueur maximale, autocomplete].
const groups = [
  {
    title: 'Vos coordonnées',
    fields: [
      ['entreprise', 'Nom de l’entreprise', 'text', 200, 'organization'],
      ['nom', 'Nom', 'text', 100, 'family-name'],
      ['prenom', 'Prénom', 'text', 100, 'given-name'],
      ['email', 'Adresse électronique', 'email', 254, 'email'],
      ['telephone', 'Téléphone', 'tel', 32, 'tel'],
    ],
  },
  {
    title: 'Votre événement',
    fields: [
      ['lieu', 'Lieu', 'text', 255, 'off'],
      ['type_evenement', 'Type d’événement envisagé', 'text', 100, 'off'],
      ['date_souhaitee', 'Date souhaitée', 'date', 10, 'off'],
      ['participants_estimes', 'Nombre de participants estimé', 'number', 10, 'off'],
    ],
  },
]

const form = reactive(Object.fromEntries([...groups.flatMap(g => g.fields.map(f => [f[0], ''])), ['besoin', '']]))

// Clé d'envoi tirée une fois à l'ouverture : renvoyer le formulaire après une réponse perdue
// retrouve la même demande au lieu d'en créer une seconde.
const newKey = () => [...crypto.getRandomValues(new Uint8Array(32))].map(b => b.toString(16).padStart(2, '0')).join('')
const key = newKey()
const busy = ref(false),
  errors = ref({}),
  message = ref(''),
  error = ref(''),
  uncertain = ref(false)

// Retire l'erreur d'un champ dès qu'il est corrigé.
function edited(id) {
  delete errors.value[id]
  if (!Object.keys(errors.value).length && error.value === 'Vérifiez les champs signalés.') error.value = ''
}

/** Envoie la demande ; le message de remerciement affiché est celui paramétré par l'administratrice. */
async function submit() {
  busy.value = true
  error.value = ''
  errors.value = {}
  try {
    if (!auth.csrf) await refresh()
    const result = await request('/api/quote-requests', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify({ ...form, submission_key: key }),
    })
    message.value = result.message
    uncertain.value = false
    await nextTick()
    document.getElementById('quote-success')?.focus()
  } catch (e) {
    errors.value = e.fields || {}
    if (e.status === 422) error.value = 'Vérifiez les champs signalés.'
    else if (e.status === 429) error.value = 'Trop de demandes. Réessayez dans quinze minutes.'
    else if (e.status === 403) {
      error.value = 'La session a expiré. Réessayez avec les mêmes informations.'
      try {
        await refresh()
      } catch {
        /* Garder la saisie. */
      }
      // Même clé d'envoi mais contenu différent : on demande de renvoyer les informations d'origine.
    } else if (e.status === 409) {
      error.value =
        'Cette tentative contient des informations différentes. Réessayez avec les informations de votre premier envoi.'
      uncertain.value = true
    } else {
      error.value =
        'Nous n’avons pas pu confirmer l’enregistrement. Vos informations sont conservées dans ce formulaire. Réessayez sans les modifier pour éviter un doublon.'
      uncertain.value = true
    }
    busy.value = false
    await nextTick()
    ;(document.getElementById(Object.keys(errors.value)[0]) || document.getElementById('quote-errors'))?.focus()
  } finally {
    busy.value = false
  }
}
</script>
<template>
  <main class="poster-page quote-page">
    <!-- Décor : aplats déchirés, étoiles et ticket « Devis » -->
    <span class="shape blue torn-a deco-a" aria-hidden="true"></span>
    <span class="shape blue torn-b deco-b" aria-hidden="true"></span>
    <span class="shape yellow star deco-c" aria-hidden="true"></span>
    <span class="shape red star deco-d" aria-hidden="true"></span>
    <span class="shape yellow torn-b deco-e" aria-hidden="true"></span>
    <span class="ticket-stamp" aria-hidden="true">★ Devis ★</span>
    <div class="quote-head">
      <h1 class="poster-title">Parlons de votre événement</h1>
      <p class="poster-lead">
        Décrivez votre projet pour que Chloé puisse vous recontacter et étudier votre demande de devis.
      </p>
    </div>
    <!-- Demande enregistrée : message de remerciement de l'agence -->
    <section v-if="message" id="quote-success" class="paper" tabindex="-1" role="status">
      <h2>Votre demande est enregistrée</h2>
      <p class="preserve-text">{{ message }}</p>
      <RouterLink to="/">Retour à l’accueil</RouterLink>
    </section>
    <form v-else @submit.prevent="submit" novalidate :aria-busy="busy">
      <p class="all-required">Tous les champs sont obligatoires.</p>
      <div v-if="error" id="quote-errors" class="error" role="alert" tabindex="-1">{{ error }}</div>
      <!-- Rubriques du formulaire, générées depuis la liste groups -->
      <fieldset v-for="group in groups" :key="group.title" class="group" :disabled="busy || uncertain">
        <legend>{{ group.title }}</legend>
        <div class="quote-grid">
          <div v-for="[id, label, type, max, autocomplete] in group.fields" :key="id">
            <label :for="id">{{ label }} <span class="req">*</span></label>
            <input
              :id="id"
              v-model="form[id]"
              @input="edited(id)"
              :type="type"
              :maxlength="max"
              :autocomplete="autocomplete"
              required
              :min="type === 'number' ? 1 : undefined"
              :step="type === 'number' ? 1 : undefined"
              :aria-invalid="!!errors[id]"
              :aria-describedby="errors[id] ? id + '-error' : undefined"
            />
            <p v-if="errors[id]" :id="id + '-error'" class="error">{{ errors[id] }}</p>
          </div>
        </div>
      </fieldset>
      <label for="besoin">Votre besoin en quelques mots <span class="req">*</span></label>
      <textarea
        id="besoin"
        v-model="form.besoin"
        @input="edited('besoin')"
        rows="5"
        maxlength="5000"
        required
        :disabled="busy || uncertain"
        :aria-invalid="!!errors.besoin"
        :aria-describedby="errors.besoin ? 'besoin-error' : undefined"
      />
      <p v-if="errors.besoin" id="besoin-error" class="error">{{ errors.besoin }}</p>
      <p v-if="busy" role="status">Enregistrement de votre demande…</p>
      <p class="submit">
        <button class="poster-button" :disabled="busy">
          {{ busy ? 'Envoi…' : uncertain ? 'Vérifier et réessayer' : 'Envoyer ma demande' }}
        </button>
      </p>
    </form>
  </main>
</template>
<style scoped>
/* Demande de devis : titre sur bande rouge, rubriques en cartes de papier à bandeau coloré. */
.quote-head {
  text-align: center;
}
.quote-head .poster-lead {
  margin: 0 auto;
  font-weight: 400;
}
.all-required {
  width: fit-content;
  margin: 16px auto 8px;
  padding: 2px 16px;
  background: #ffd836;
}
.group {
  margin: 32px 0;
  padding: 0 32px 28px;
  border: 0;
  min-width: 0;
  background: #fbf8f1;
  border-radius: 6px;
}
legend {
  float: left;
  width: calc(100% + 64px);
  margin: 0 -32px 8px;
  padding: 6px 32px;
  font-family: Anton, Impact, 'Arial Narrow', sans-serif;
  font-size: clamp(28px, 4vw, 40px);
  font-weight: 400;
  text-transform: uppercase;
  background: #a9b8f0;
  clip-path: polygon(0 10%, 30% 0, 60% 8%, 100% 2%, 99% 92%, 70% 100%, 35% 92%, 0 100%);
}
.group:nth-of-type(2) legend {
  background: #ffd836;
}
.quote-grid {
  clear: both;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 0 24px;
}
textarea {
  resize: vertical;
}
.submit {
  margin-top: 32px;
  text-align: center;
}
.preserve-text {
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.ticket-stamp {
  top: 330px;
  right: 24px;
  z-index: 2;
}
.deco-a {
  top: 60px;
  left: -80px;
  width: 260px;
  height: 300px;
  transform: rotate(-10deg);
}
.deco-b {
  top: 130px;
  right: -60px;
  width: 340px;
  height: 200px;
  transform: rotate(-8deg);
}
.star {
  clip-path: polygon(
    50% 0,
    61% 32%,
    96% 22%,
    70% 50%,
    98% 78%,
    62% 68%,
    50% 100%,
    38% 68%,
    2% 78%,
    30% 50%,
    4% 22%,
    39% 32%
  );
}
.deco-c {
  top: 420px;
  left: -60px;
  width: 200px;
  height: 260px;
}
.deco-d {
  top: 900px;
  right: -40px;
  width: 160px;
  height: 200px;
}
.deco-e {
  bottom: 40px;
  right: -60px;
  width: 300px;
  height: 140px;
  transform: rotate(-12deg);
}
@media (max-width: 600px) {
  .quote-grid {
    grid-template-columns: 1fr;
  }
  .group {
    padding: 0 16px 20px;
  }
  legend {
    width: calc(100% + 32px);
    margin: 0 -16px 8px;
    padding: 6px 16px;
  }
}
</style>
