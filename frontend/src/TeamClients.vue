<script setup>
// Liste des clients (équipe) avec recherche ; l'administratrice peut aussi créer une fiche.
import { nextTick, onMounted, ref } from 'vue'
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
  <main class="panel wide">
    <p><RouterLink to="/espace">← Retour à mon espace</RouterLink></p>
    <h1>Clients</h1>
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
    <form class="search" role="search" @submit.prevent="load">
      <label for="clients-q">Rechercher un client (entreprise, contact, adresse, ville)</label>
      <div class="row">
        <input id="clients-q" v-model="q" type="search" maxlength="100" /><button type="submit">Rechercher</button>
      </div>
    </form>
    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" role="status">Chargement…</p>
    <p v-else role="status">
      {{ clients.length }} client{{ clients.length > 1 ? 's' : '' }}{{ searched ? ` pour « ${searched} »` : '' }}.
    </p>
    <!-- Liste des clients -->
    <ul class="cards">
      <li v-for="c in clients" :key="c.id">
        <h2>
          <RouterLink :to="'/equipe/clients/' + c.id">{{ c.entreprise }}</RouterLink>
        </h2>
        <p class="meta">
          {{ c.contact }}<template v-if="c.ville"> — {{ c.ville }}</template> — {{ c.evenements }} événement{{
            c.evenements > 1 ? 's' : ''
          }}
        </p>
        <p class="meta">
          <a :href="'tel:' + c.telephone">{{ c.telephone }}</a> — <a :href="'mailto:' + c.email">{{ c.email }}</a>
        </p>
      </li>
    </ul>
  </main>
</template>
<style scoped>
.row {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}
.row input {
  flex: 1 1 220px;
}
.cards {
  list-style: none;
  padding: 0;
}
.cards li {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 16px;
  margin: 12px 0;
}
h2 {
  margin: 0 0 4px;
  font-size: 1.15rem;
  overflow-wrap: anywhere;
}
.meta {
  margin: 4px 0;
  color: #4a5b68;
  overflow-wrap: anywhere;
}
.box {
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 16px;
  margin: 16px 0;
}
.success {
  color: #0b6b3a;
  font-weight: 600;
}
</style>
