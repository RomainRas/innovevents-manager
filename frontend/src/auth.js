// Session de l'utilisateur et appels à l'API, partagés par tous les écrans du site.
import { reactive } from 'vue'

// État réactif : utilisateur connecté (ou null), jeton CSRF à joindre à chaque écriture, session déjà lue.
export const auth = reactive({ user: null, csrf: '', loaded: false })

/**
 * Appel à l'API sur la même origine (cookie de session envoyé), abandonné après 15 secondes.
 * En cas d'erreur HTTP, lève une Error dont le message est le code renvoyé par l'API,
 * avec le statut HTTP (status) et les erreurs par champ (fields).
 */
export async function request(path, options = {}) {
  const response = await fetch(path, {
    credentials: 'same-origin',
    ...options,
    headers: { ...options.headers },
    signal: AbortSignal.timeout(15000),
  })
  const data = await response.json()
  if (!response.ok)
    throw Object.assign(new Error(data.error || 'unavailable'), { status: response.status, fields: data.fields || {} })
  return data
}

/** Relit la session courante et le jeton CSRF. */
export async function refresh() {
  const data = await request('/api/auth/session')
  auth.user = data.user
  auth.csrf = data.csrf
  auth.loaded = true
}

/** Connexion : le serveur renouvelle la session et renvoie un nouveau jeton CSRF. */
export async function login(email, password) {
  if (!auth.csrf) await refresh()
  const data = await request('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
    body: JSON.stringify({ email, password }),
  })
  auth.user = data.user
  auth.csrf = data.csrf
}

/** Déconnexion : la session est détruite, un jeton CSRF neuf est fourni pour la suite. */
export async function logout() {
  const data = await request('/api/auth/logout', { method: 'POST', headers: { 'X-CSRF-Token': auth.csrf } })
  auth.user = null
  auth.csrf = data.csrf
}
