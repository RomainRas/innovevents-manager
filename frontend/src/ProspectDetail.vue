<script setup>
// Fiche d'une demande de devis (administratrice) : détail de la demande, conversion en client
// ou qualification en échec avec un message envoyé au prospect.
import { nextTick, onMounted, ref } from 'vue'
import Icon from './Icon.vue'
import { useRoute } from 'vue-router'
import { auth, refresh, request } from './auth'

const route = useRoute(),
  prospect = ref(null),
  loading = ref(true),
  busy = ref(false),
  error = ref(''),
  message = ref(''),
  confirmed = ref(false),
  saved = ref(false),
  uncertain = ref(false)
const labels = { a_contacter: 'À contacter', echoue: 'Échoué', converti: 'Converti' }

// Champs de la demande affichés, dans l'ordre, avec leur libellé.
// Couleur de la pastille de statut.
const tones = { a_contacter: '', echoue: 'red', converti: 'green' }
const fields = {
  nom: 'Nom',
  prenom: 'Prénom',
  email: 'Adresse électronique',
  telephone: 'Téléphone',
  lieu: 'Lieu',
  type_evenement: 'Type d’événement',
  date_souhaitee: 'Date souhaitée',
  participants_estimes: 'Participants estimés',
  besoin: 'Besoin',
}

async function load() {
  loading.value = true
  error.value = ''
  try {
    prospect.value = (await request('/api/admin/prospect?' + new URLSearchParams({ id: route.params.id }))).prospect
  } catch (e) {
    error.value =
      e.status === 403
        ? 'Cet espace est réservé à l’administratrice.'
        : e.status === 404
          ? 'Demande introuvable.'
          : e.status === 401
            ? 'Votre session a expiré. Reconnectez-vous.'
            : 'Chargement impossible. Réessayez.'
  } finally {
    loading.value = false
  }
}

/** Qualifie la demande en échec : le message est enregistré et envoyé par courriel au prospect. */
async function submit() {
  busy.value = true
  error.value = ''
  try {
    await request('/api/admin/prospect-failure', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify({ id: route.params.id, message: message.value }),
    })
    prospect.value.statut = 'echoue'
    prospect.value.message_echec = message.value.trim()
    saved.value = true
    uncertain.value = false
    await nextTick()
    document.getElementById('qualification-result')?.focus()
  } catch (e) {
    if (e.status === 422) error.value = 'Saisissez un message de 1 à 2 000 caractères.'
    else if (e.status === 409) {
      await load()
      error.value = 'Cette demande a déjà été traitée. Consultez son état actualisé.'
    } else if (e.status === 401 || e.status === 403) {
      error.value = 'La session ou vos droits ne permettent plus cette action. Reconnectez-vous si nécessaire.'
      try {
        await refresh()
      } catch {
        /* Préserver le texte. */
      }
    } else {
      uncertain.value = true
      error.value =
        'Nous n’avons pas pu confirmer le résultat. Réessayez avec le même message : aucun second courriel ne sera ajouté pour cette action déjà enregistrée.'
    }
    busy.value = false
    await nextTick()
    document.getElementById('qualification-error')?.focus()
  } finally {
    busy.value = false
  }
}

onMounted(load)
</script>
<template>
  <main class="work-page">
    <RouterLink class="back-link" to="/prospects">← Retour aux demandes</RouterLink>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-if="error" id="qualification-error" role="alert" tabindex="-1" class="error">{{ error }}</p>
    <template v-if="prospect">
      <h1 class="list-title">Demande — {{ prospect.entreprise }}</h1>
      <p class="lead">Détail d’une demande de devis</p>
      <!-- Statut de la demande sur bandeau jaune -->
      <p class="status-band">
        <Icon name="mail" /><span>Statut de la demande</span
        ><strong class="status-pill" :class="tones[prospect.statut]">{{ labels[prospect.statut] }}</strong>
      </p>
      <div class="layout">
        <div class="main-col">
          <!-- Coordonnées du contact -->
          <section class="info-card blue" aria-labelledby="contact-title">
            <h2 id="contact-title"><Icon name="user" />Coordonnées</h2>
            <dl>
              <template v-for="key in ['nom', 'prenom', 'email', 'telephone']" :key="key">
                <dt>{{ fields[key] }}</dt>
                <dd>
                  <a v-if="key === 'email'" :href="'mailto:' + prospect.email">{{ prospect.email }}</a
                  ><a v-else-if="key === 'telephone'" :href="'tel:' + prospect.telephone">{{ prospect.telephone }}</a
                  ><template v-else>{{ prospect[key] }}</template>
                </dd>
              </template>
            </dl>
          </section>
          <!-- Projet d'événement -->
          <section class="info-card red" aria-labelledby="project-title">
            <h2 id="project-title"><Icon name="calendar" />Projet d’événement</h2>
            <dl>
              <template v-for="key in ['type_evenement', 'date_souhaitee', 'lieu', 'participants_estimes']" :key="key">
                <dt>{{ fields[key] }}</dt>
                <dd>
                  {{ key === 'date_souhaitee' ? String(prospect[key]).split('-').reverse().join('/') : prospect[key] }}
                </dd>
              </template>
            </dl>
          </section>
          <!-- Besoin exprimé par le prospect -->
          <section class="info-card yellow" aria-labelledby="need-title">
            <h2 id="need-title"><Icon name="file" />{{ fields.besoin }}</h2>
            <p class="need">{{ prospect.besoin }}</p>
          </section>
        </div>
        <!-- Actions : conversion en client, fiche client, ou classement en échec -->
        <aside class="actions-card" aria-labelledby="actions-title">
          <h2 id="actions-title">Actions</h2>
          <div v-if="prospect.statut === 'a_contacter'" class="action">
            <h3><Icon name="users" />Convertir en client</h3>
            <p>Créer la fiche client à partir de cette demande (entreprise et contact).</p>
            <RouterLink class="open-link" :to="'/prospects/' + prospect.id + '/convertir'"
              >Convertir en client</RouterLink
            >
          </div>
          <div v-if="prospect.statut === 'converti'" class="action">
            <RouterLink class="open-link" :to="'/clients/' + prospect.client_id">Consulter la fiche client</RouterLink>
          </div>
          <!-- Demande à traiter : qualification en échec -->
          <section v-if="prospect.statut === 'a_contacter'" class="action">
            <h3>Le projet ne peut pas aboutir ?</h3>
            <p>
              Après votre échange avec le prospect, expliquez pourquoi l’agence ne peut pas donner suite. Ce message lui
              sera envoyé par courriel.
            </p>
            <form @submit.prevent="submit" :aria-busy="busy">
              <label for="failure-message">Message au prospect</label
              ><textarea
                id="failure-message"
                v-model="message"
                maxlength="2000"
                rows="6"
                required
                :disabled="busy || uncertain"
              />
              <label class="confirmation"
                ><input type="checkbox" v-model="confirmed" required :disabled="busy || uncertain" />Je confirme le
                classement en « échoué » et l’envoi de ce message.</label
              >
              <button :disabled="busy || !confirmed">
                {{ busy ? 'Enregistrement…' : uncertain ? 'Vérifier et réessayer' : 'Classer en échoué et notifier' }}
              </button>
            </form>
          </section>
          <!-- Demande en échec : message envoyé au prospect -->
          <section v-if="prospect.statut === 'echoue'" class="action">
            <h3>Message enregistré pour le prospect</h3>
            <p class="message">{{ prospect.message_echec }}</p>
          </section>
          <p v-if="saved" id="qualification-result" role="status" tabindex="-1">
            Demande classée en échoué. Le message a été mis en attente d’envoi.
          </p>
        </aside>
      </div>
    </template>
  </main>
</template>
<style scoped>
/* Fiche d'une demande : bandeau de statut, cartes d'informations à liseré, colonne d'actions. */
.lead {
  margin: -8px 0 20px;
  color: var(--texte-secondaire);
}
.status-band {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px 20px;
  margin: 0 0 24px;
  padding: 16px 22px;
  background: #fbf0cf;
  border-left: 6px solid var(--jaune);
  border-radius: 6px;
  font-size: 1.05rem;
}
.status-band .status-pill {
  font-size: 1.05rem;
}
.layout {
  display: grid;
  grid-template-columns: minmax(0, 1.5fr) minmax(280px, 1fr);
  gap: 24px;
  align-items: start;
}
.info-card,
.actions-card {
  margin: 0 0 20px;
  padding: 4px 22px 18px;
  background: var(--creme);
  border: 1px solid var(--filet);
  border-left: 6px solid var(--accent, var(--rouge));
  border-radius: 8px;
}
.info-card.blue {
  --accent: var(--bleu);
}
.info-card.yellow {
  --accent: var(--jaune);
}
.actions-card {
  border-left: 1px solid var(--filet);
}
h2 {
  display: flex;
  align-items: center;
  gap: 12px;
  margin: 14px 0 10px;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--filet);
  font-family: var(--police-titre);
  font-weight: 400;
  font-size: 1.6rem;
  color: var(--encre);
}
dl {
  display: grid;
  grid-template-columns: minmax(130px, 200px) minmax(0, 1fr);
  margin: 0;
}
dt,
dd {
  margin: 0;
  padding: 9px 0;
  border-bottom: 1px solid var(--filet-clair);
}
dt {
  color: var(--texte-secondaire);
}
dd {
  font-weight: 600;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
dd a {
  color: var(--bleu);
}
.need,
.message {
  margin: 0;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
}
.action {
  padding: 14px 0;
  border-top: 1px solid var(--filet);
}
.action:first-of-type {
  border-top: 0;
}
h3 {
  display: flex;
  align-items: center;
  gap: 10px;
  margin: 0 0 6px;
  font-size: 1.15rem;
}
.action p {
  margin: 0 0 12px;
  color: var(--texte-secondaire);
}
.action .open-link {
  width: 100%;
  justify-content: center;
  border-color: var(--bleu);
  color: var(--bleu);
}
.confirmation {
  display: flex;
  gap: 12px;
  align-items: center;
  font-weight: 400;
}
.confirmation input {
  width: 22px;
  min-height: 22px;
  flex-shrink: 0;
}
form button {
  margin-top: 8px;
  background: var(--nuit) !important;
}
@media (max-width: 900px) {
  .layout {
    grid-template-columns: minmax(0, 1fr);
  }
}
@media (max-width: 560px) {
  dl {
    grid-template-columns: minmax(0, 1fr);
  }
  dt {
    padding-bottom: 0;
    border-bottom: 0;
  }
}
</style>
