// Service worker de l'application mobile Innov'Events (portée /mobile/).
// - Coquille de l'application : servie depuis le cache si le réseau manque.
// - Données de consultation (/api/mobile/events, event, client) : réseau d'abord, dernière réponse en secours hors ligne.
// - Aucune écriture (POST) n'est mise en cache ni rejouée. Les données sont effacées à la déconnexion ou si la session expire.
const SHELL = 'ie-mobile-shell-v1'
const DATA = 'ie-mobile-data'
const SHELL_FILES = ['/mobile/', '/mobile/manifest.webmanifest', '/mobile/icon.svg', '/mobile/icon-192.png']
const DATA_PATHS = /^\/api\/mobile\/(events|event|client)$/

self.addEventListener('install', event => {
  event.waitUntil(caches.open(SHELL).then(cache => cache.addAll(SHELL_FILES)).then(() => self.skipWaiting()))
})
self.addEventListener('activate', event => {
  event.waitUntil(caches.keys()
    .then(keys => Promise.all(keys.filter(k => k.startsWith('ie-mobile-shell-') && k !== SHELL).map(k => caches.delete(k))))
    .then(() => self.clients.claim()))
})
self.addEventListener('message', event => {
  if (event.data?.type === 'clear-data') event.waitUntil(caches.delete(DATA))
})

async function data(request) {
  try {
    const response = await fetch(request)
    if (response.ok) { const cache = await caches.open(DATA); await cache.put(request, response.clone()) }
    else if (response.status === 401 || response.status === 403) await caches.delete(DATA)
    return response
  } catch {
    const cached = await (await caches.open(DATA)).match(request)
    if (!cached) return new Response(JSON.stringify({ error: 'offline' }), { status: 503, headers: { 'Content-Type': 'application/json' } })
    // Signale à l'application que la réponse vient du cache (affichage « hors ligne »).
    const headers = new Headers(cached.headers); headers.set('X-Hors-Ligne', '1')
    return new Response(await cached.blob(), { status: cached.status, headers })
  }
}
async function page(request) {
  try { return await fetch(request) } catch { return (await caches.match('/mobile/')) || Response.error() }
}
// Fichiers de l'application : réseau d'abord (toujours la dernière version), copie en cache pour l'usage hors ligne.
async function asset(request) {
  const cache = await caches.open(SHELL)
  try {
    const response = await fetch(request)
    if (response.ok) await cache.put(request, response.clone())
    return response
  } catch { return (await cache.match(request)) || Response.error() }
}

self.addEventListener('fetch', event => {
  const { request } = event
  if (request.method !== 'GET') return
  const url = new URL(request.url)
  if (url.origin !== self.location.origin) return
  if (DATA_PATHS.test(url.pathname)) return event.respondWith(data(request))
  if (url.pathname.startsWith('/api/')) return
  if (request.mode === 'navigate' && url.pathname.startsWith('/mobile')) return event.respondWith(page(request))
  if (url.pathname.startsWith('/mobile/')) return event.respondWith(asset(request))
})
