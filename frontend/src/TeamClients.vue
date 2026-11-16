<script setup>
// Liste des clients (équipe) avec recherche ; l'administratrice peut aussi créer une fiche.
import { nextTick, onMounted, ref } from 'vue'
import Icon from './Icon.vue'
import { useRoute, useRouter } from 'vue-router'
import { auth, request } from './auth'
import ClientForm from './ClientForm.vue'

const route = useRoute(),
  router = useRouter()
const clients = ref([]),
  q = ref(''),
  loading = ref(true),
  error = ref(''),
  searched = ref(''),
  creating = ref(false)

// Message transmis par l'adresse après la suppression d'une fiche.
const notice = ref(route.query.message === 'supprime' ? 'Fiche client supprimée.' : '')

// Ouvre le formulaire de création et y place le focus ; annuler rend le focus au bouton.
async function startCreate() {
  creating.value = true
  notice.value = ''
  await nextTick()
  document.getElementById('client-new-entreprise')?.focus()
}

async function cancelCreate() {
  creating.value = false
  await nextTick()
  document.getElementById('client-new-button')?.focus()
}

// Après création, ouverture de la nouvelle fiche avec un message de confirmation.
const created = id => router.push({ path: '/equipe/clients/' + id, query: { message: 'cree' } })

/** Charge les clients correspondant à la recherche (entreprise, contact ou adresse électronique). */
async function load() {
  loading.value = true
  error.value = ''
  try {
    clients.value = (await request('/api/team/clients?' + new URLSearchParams({ q: q.value }))).clients
    searched.value = q.value.trim()
  } catch {
    error.value = 'La liste des clients est indisponible pour le moment.'
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
<template>
  <main class="work-page">
    <p><RouterLink class="back-link" to="/espace">← Retour à mon espace</RouterLink></p>
    <h1 class="list-title" style="--bar: #ffd836">Clients</h1>
    <p v-if="notice" class="success" role="status">{{ notice }}</p>
    <!-- Création d'une fiche (administratrice) -->
    <template v-if="auth.user?.role === 'administrateur'">
      <button v-if="!creating" id="client-new-button" type="button" @click="startCreate">Nouveau client</button>
      <section v-else aria-labelledby="client-new-title" class="box">
        <h2 id="client-new-title">Nouveau client</h2>
        <ClientForm submit-label="Créer la fiche client" @saved="created" @cancel="cancelCreate" />
      </section>
    </template>
    <!-- Recherche -->
    <form class="search-bar" role="search" @submit.prevent="load">
      <div class="search-field">
        <label for="clients-q">Rechercher un client (entreprise, contact, adresse, ville)</label>
        <input id="clients-q" v-model="q" type="search" maxlength="100" /><Icon name="search" />
      </div>
      <button type="submit">Rechercher</button>
    </form>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-else role="status" class="count">
      {{ clients.length }} client{{ clients.length > 1 ? 's' : '' }}{{ searched ? ` pour « ${searched} »` : '' }}.
    </p>
    <!-- Liste des clients -->
    <ul class="result-list">
      <li v-for="c in clients" :key="c.id">
        <span class="result-tile yellow"><Icon name="building" /></span>
        <div class="result-main">
          <h2>
            <RouterLink :to="'/equipe/clients/' + c.id">{{ c.entreprise }}</RouterLink>
          </h2>
          <p class="result-meta">
            {{ c.contact }}<template v-if="c.ville"> — {{ c.ville }}</template> —
            <span class="status-pill">{{ c.evenements }} événement{{ c.evenements > 1 ? 's' : '' }}</span>
          </p>
          <p class="result-meta contact">
            <Icon name="phone" /><a :href="'tel:' + c.telephone">{{ c.telephone }}</a> — <Icon name="mail" /><a
              :href="'mailto:' + c.email"
              >{{ c.email }}</a
            >
          </p>
        </div>
        <!-- Indication visuelle : toute la carte ouvre la fiche, via le lien du titre -->
        <span class="file-link" aria-hidden="true">Voir le dossier client <Icon name="arrow" /></span>
      </li>
    </ul>
  </main>
</template>
<style scoped>
.count {
  font-weight: 700;
  font-size: 1.15rem;
}
.contact {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 6px 8px;
}
.contact a {
  color: #1d3fbf;
}
.file-link {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding-left: 24px;
  border-left: 1px solid #d8cfbd;
  color: #a51b14;
  font-weight: 700;
}
.result-tile {
  width: 72px;
  height: 72px;
}
.box {
  padding: 20px;
  margin: 16px 0;
  background: #fbf8f1;
  border: 1px solid #d8cfbd;
  border-radius: 8px;
}
#client-new-button {
  margin-bottom: 16px;
}
.success {
  color: #0b6b3a;
  font-weight: 600;
}
</style>
