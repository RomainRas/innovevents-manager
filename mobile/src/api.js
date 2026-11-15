import { reactive } from 'vue'
// État partagé : utilisateur connecté, jeton CSRF et indicateur hors ligne (réponse servie par le cache).
export const state = reactive({ user: null, csrf: '', loaded: false, offline: !navigator.onLine })
addEventListener('online', () => { state.offline = false })
addEventListener('offline', () => { state.offline = true })

export async function api(path, options = {}) {
  let response
  try {
    response = await fetch(path, { credentials: 'same-origin', ...options, headers: { ...options.headers }, signal: AbortSignal.timeout(15000) })
  } catch {
    throw Object.assign(new Error('offline'), { status: 0 })
  }
  const data = await response.json().catch(() => ({}))
  if (!response.ok) throw Object.assign(new Error(data.error || 'unavailable'), { status: response.status, fields: data.fields || {} })
  if (path.startsWith('/api/mobile/')) state.offline = response.headers.get('X-Hors-Ligne') === '1' || !navigator.onLine
  return data
}
export const post = (path, body) => api(path, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': state.csrf }, body: JSON.stringify(body) })

// Dernier utilisateur connecté (prénom et rôle seulement) : permet d'ouvrir l'application hors ligne
// pour relire les données en cache. Effacé à la déconnexion ou dès que le serveur indique qu'aucune session n'est ouverte.
const LAST_USER = 'ie-mobile-user'
function remember(user) {
  try { user ? localStorage.setItem(LAST_USER, JSON.stringify({ id: user.id, prenom: user.prenom, role: user.role })) : localStorage.removeItem(LAST_USER) } catch { /* Stockage indisponible. */ }
}
export async function session() {
  try {
    const data = await api('/api/auth/session')
    state.user = data.user; state.csrf = data.csrf; remember(data.user)
  } catch (e) {
    // Hors ligne : reprise du dernier utilisateur pour la consultation seule ; toute écriture redemandera le réseau.
    let last = null
    try { last = JSON.parse(localStorage.getItem(LAST_USER) || 'null') } catch { /* Stockage indisponible. */ }
    if (e.status !== 0 || !last) throw e
    state.user = last; state.offline = true
  } finally { state.loaded = true }
}
export async function login(email, password) {
  if (!state.csrf) await session()
  const data = await post('/api/auth/login', { email, password })
  state.user = data.user; state.csrf = data.csrf; remember(data.user)
}
/** Déconnexion : session fermée et données client effacées du téléphone (cache du service worker). */
export async function logout() {
  try { const data = await post('/api/auth/logout', {}); state.csrf = data.csrf } catch { /* Hors ligne : effacement local quand même. */ }
  state.user = null
  clearCachedData()
}
export function clearCachedData() {
  remember(null)
  if ('caches' in window) caches.delete('ie-mobile-data').catch(() => { /* Rien à effacer. */ })
  navigator.serviceWorker?.controller?.postMessage({ type: 'clear-data' })
  try { for (const key of Object.keys(localStorage)) if (key.startsWith('ie-note-')) localStorage.removeItem(key) } catch { /* Stockage indisponible. */ }
}

/** Brouillon de note conservé sur l'appareil si l'enregistrement échoue (réseau perdu). */
export const draft = {
  get(id) { try { return localStorage.getItem('ie-note-' + id) || '' } catch { return '' } },
  set(id, text) { try { text ? localStorage.setItem('ie-note-' + id, text) : localStorage.removeItem('ie-note-' + id) } catch { /* Stockage indisponible. */ } },
}

export const statuses = { brouillon: 'Brouillon', accepte: 'Accepté', en_cours: 'En cours', termine: 'Terminé', annule: 'Annulé' }
export function when(debut, fin) {
  const p = v => /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(v || '')
  const a = p(debut), b = p(fin)
  if (!a) return 'Date à définir'
  const start = `${a[3]}/${a[2]}/${a[1]} à ${a[4]} h ${a[5]}`
  if (!b) return start
  return a[1] === b[1] && a[2] === b[2] && a[3] === b[3] ? `${start} – ${b[4]} h ${b[5]}` : `${start} au ${b[3]}/${b[2]}/${b[1]} à ${b[4]} h ${b[5]}`
}
export function day(debut) {
  const m = /^(\d{4})-(\d{2})-(\d{2})T/.exec(debut || '')
  if (!m) return { d: '?', m: '' }
  return { d: m[3], m: new Date(Date.UTC(+m[1], +m[2] - 1, 1)).toLocaleDateString('fr-FR', { month: 'short', timeZone: 'UTC' }) }
}
