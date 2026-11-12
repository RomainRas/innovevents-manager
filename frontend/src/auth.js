import { reactive } from 'vue'
export const auth = reactive({ user: null, csrf: '', loaded: false })
export async function request(path, options = {}) {
  const response = await fetch(path, { credentials: 'same-origin', ...options,
    headers: { ...options.headers }, signal: AbortSignal.timeout(15000) })
  const data = await response.json()
  if (!response.ok) throw Object.assign(new Error(data.error || 'unavailable'), { status: response.status, fields: data.fields || {} })
  return data
}
export async function refresh() {
  const data = await request('/api/auth/session')
  auth.user = data.user; auth.csrf = data.csrf; auth.loaded = true
}
export async function login(email, password) {
  if (!auth.csrf) await refresh()
  const data = await request('/api/auth/login', { method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': auth.csrf },
    body: JSON.stringify({ email, password }) })
  auth.user = data.user; auth.csrf = data.csrf
}
export async function logout() {
  const data = await request('/api/auth/logout', { method: 'POST', headers: { 'X-CSRF-Token': auth.csrf } })
  auth.user = null; auth.csrf = data.csrf
}
