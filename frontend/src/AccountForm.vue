<script setup>
// Formulaire de compte partagé par trois pages (meta.accountMode) : inscription, mot de passe oublié
// et changement du mot de passe (obligatoire à la première connexion d'un compte créé par l'agence).
import { computed, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { auth, refresh, request } from './auth'

const route = useRoute()
const mode = computed(() => route.meta.accountMode)
const titles = { register: 'Créer votre compte', forgot: 'Mot de passe oublié', password: 'Changer votre mot de passe' }
const fields = reactive({
  email: '',
  prenom: '',
  nom: '',
  nom_utilisateur: '',
  current_password: '',
  password: '',
  confirmation: '',
})

const busy = ref(false),
  error = ref(''),
  success = ref('')

// Codes d'erreur de l'API traduits en messages compréhensibles.
const messages = {
  password_policy:
    'Choisissez au moins 8 caractères avec une majuscule, une minuscule, un chiffre et un caractère spécial (72 octets maximum).',
  password_confirmation: 'Les deux mots de passe ne correspondent pas.',
  current_password_invalid: 'Le mot de passe actuel est incorrect.',
  password_must_change: 'Choisissez un mot de passe différent du précédent.',
  invalid_email: 'Vérifiez votre adresse électronique.',
  invalid_identity: 'Vérifiez les informations de votre identité.',
  too_many_attempts: 'Trop de tentatives. Réessayez dans quinze minutes.',
  authentication_required: 'Reconnectez-vous avant de changer votre mot de passe.',
}

/**
 * Envoie le formulaire au point d'API du mode courant. Les messages de succès de l'inscription et du mot de passe
 * oublié restent neutres : ils ne révèlent pas si une adresse a déjà un compte.
 */
async function submit() {
  busy.value = true
  error.value = ''
  success.value = ''
  try {
    if (!auth.csrf) await refresh()
    const data = await request(`/api/auth/${mode.value}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
      body: JSON.stringify(fields),
    })
    // Après un changement de mot de passe, toutes les sessions sont fermées : il faut se reconnecter.
    if (mode.value === 'password') {
      auth.user = null
      auth.csrf = data.csrf
    }
    success.value =
      mode.value === 'register'
        ? 'Si votre inscription peut être créée, un courriel de confirmation vous sera envoyé. Vous pouvez ensuite vous connecter.'
        : mode.value === 'forgot'
          ? 'Si un compte actif correspond à cette adresse, vous recevrez un mot de passe temporaire. Il est valable 30 minutes. Vérifiez aussi vos courriels indésirables.'
          : 'Votre mot de passe a été changé. Connectez-vous avec le nouveau mot de passe.'
  } catch (e) {
    error.value = messages[e.message] || 'La demande n’a pas pu aboutir. Réessayez dans un instant.'
    if (e.status === 403) {
      try {
        await refresh()
      } catch {
        /* Garder le message. */
      }
    }
  } finally {
    // Les mots de passe ne restent jamais affichés dans le formulaire, succès ou échec.
    fields.password = ''
    fields.confirmation = ''
    fields.current_password = ''
    busy.value = false
  }
}
</script>
<template>
  <main class="poster-page account-page">
    <!-- Décor : aplats déchirés rouge, jaune et bleu, ticket et étoile -->
    <span class="shape red torn-a deco-a" aria-hidden="true"></span>
    <span class="shape yellow torn-b deco-b" aria-hidden="true"></span>
    <span class="shape yellow torn-a deco-c" aria-hidden="true"></span>
    <span class="shape blue torn-b deco-d" aria-hidden="true"></span>
    <svg class="ticket-deco" aria-hidden="true" viewBox="0 0 200 110" width="190" height="105">
      <path
        d="M10 18h180v24a14 14 0 0 0 0 28v24H10V70a14 14 0 0 0 0-28z"
        fill="#e8461e"
        stroke="#b9300f"
        stroke-width="3"
      />
      <rect x="32" y="30" width="136" height="52" fill="none" stroke="#111" stroke-width="2" />
      <path d="m48 46 3 7h7l-6 4 2 7-6-4-6 4 2-7-6-4h7zM152 66l3 7h7l-6 4 2 7-6-4-6 4 2-7-6-4h7z" fill="#111" />
    </svg>
    <span class="star-deco" aria-hidden="true"></span>
    <div class="paper account-card">
      <h1 class="poster-title yellow">{{ titles[mode] }}</h1>
      <p v-if="mode === 'password' && auth.user?.password_change_required">
        Vous devez choisir un nouveau mot de passe avant d’accéder à votre espace.
      </p>
      <p v-if="success" role="status">{{ success }}</p>
      <form v-else @submit.prevent="submit" :aria-busy="busy">
        <!-- Adresse électronique : inscription et mot de passe oublié -->
        <template v-if="mode !== 'password'">
          <label for="account-email">Adresse électronique</label
          ><input
            id="account-email"
            v-model="fields.email"
            type="email"
            autocomplete="email"
            required
            maxlength="254"
            :disabled="busy"
          />
        </template>
        <!-- Identité : inscription seulement -->
        <template v-if="mode === 'register'">
          <label for="first-name">Prénom</label
          ><input
            id="first-name"
            v-model="fields.prenom"
            autocomplete="given-name"
            required
            maxlength="100"
            :disabled="busy"
          />
          <label for="last-name">Nom</label
          ><input
            id="last-name"
            v-model="fields.nom"
            autocomplete="family-name"
            required
            maxlength="100"
            :disabled="busy"
          />
          <label for="username">Nom d’utilisateur</label
          ><input
            id="username"
            v-model="fields.nom_utilisateur"
            autocomplete="nickname"
            required
            maxlength="100"
            :disabled="busy"
          />
        </template>
        <!-- Mot de passe actuel : changement de mot de passe seulement -->
        <template v-if="mode === 'password'">
          <label for="current-password">Mot de passe actuel ou temporaire</label
          ><input
            id="current-password"
            v-model="fields.current_password"
            type="password"
            autocomplete="current-password"
            required
            :disabled="busy"
          />
        </template>
        <!-- Nouveau mot de passe et confirmation, avec les règles affichées -->
        <template v-if="mode !== 'forgot'">
          <label for="new-password">{{ mode === 'register' ? 'Mot de passe' : 'Nouveau mot de passe' }}</label
          ><input
            id="new-password"
            v-model="fields.password"
            type="password"
            autocomplete="new-password"
            required
            minlength="8"
            aria-describedby="password-help"
            :disabled="busy"
          />
          <p id="password-help">
            Au moins 8 caractères : majuscule, minuscule, chiffre et caractère spécial. Maximum 72 octets.
          </p>
          <label for="confirmation">Confirmer le mot de passe</label
          ><input
            id="confirmation"
            v-model="fields.confirmation"
            type="password"
            autocomplete="new-password"
            required
            :disabled="busy"
          />
        </template>
        <p role="alert" class="error">{{ error }}</p>
        <button class="poster-button" :disabled="busy">
          {{
            busy
              ? 'Envoi…'
              : mode === 'register'
                ? 'Créer le compte'
                : mode === 'forgot'
                  ? 'Recevoir un mot de passe temporaire'
                  : 'Enregistrer le nouveau mot de passe'
          }}
        </button>
      </form>
      <p class="back"><RouterLink to="/connexion">Retour à la connexion</RouterLink></p>
    </div>
  </main>
</template>
<style scoped>
/* Formulaires de compte (inscription, mot de passe oublié, changement) : feuille déchirée sur aplats colorés. */
.account-card {
  position: relative;
  z-index: 1;
  max-width: 760px;
  margin: 0 auto;
}
.account-card .poster-title {
  display: block;
  width: fit-content;
  margin: 0 auto 28px;
  font-size: clamp(40px, 6vw, 72px);
}
.account-card form {
  max-width: 600px;
  margin: 0 auto;
}
.account-card label {
  margin-top: 16px;
  font-family: Georgia, 'Times New Roman', serif;
  font-size: 1.1rem;
}
#password-help {
  margin: 6px 0 0;
  font-size: 0.9rem;
}
.poster-button {
  display: block;
  margin: 8px auto 0;
}
.back {
  margin: 20px 0 0;
  text-align: center;
  font-size: 1.1rem;
}
.ticket-deco {
  position: absolute;
  z-index: 2;
  top: 90px;
  right: max(16px, calc((100% - 760px) / 2 - 120px));
  transform: rotate(14deg);
}
.star-deco {
  position: absolute;
  z-index: 2;
  top: 70px;
  right: max(0px, calc((100% - 760px) / 2 - 170px));
  width: 110px;
  height: 110px;
  background: var(--jaune);
  clip-path: polygon(
    50% 0,
    60% 34%,
    96% 22%,
    70% 50%,
    98% 80%,
    62% 66%,
    50% 100%,
    38% 66%,
    4% 80%,
    30% 50%,
    4% 22%,
    40% 34%
  );
}
.deco-a {
  top: -20px;
  left: -60px;
  width: 520px;
  height: 340px;
  transform: rotate(-6deg);
}
.deco-b {
  bottom: 40px;
  left: -80px;
  width: 360px;
  height: 160px;
  transform: rotate(8deg);
}
.deco-c {
  top: 420px;
  right: -60px;
  width: 360px;
  height: 140px;
  transform: rotate(-24deg);
}
.deco-d {
  bottom: 20px;
  right: -60px;
  width: 520px;
  height: 340px;
  transform: rotate(-14deg);
}
@media (max-width: 1100px) {
  .ticket-deco,
  .star-deco {
    display: none;
  }
}
</style>
