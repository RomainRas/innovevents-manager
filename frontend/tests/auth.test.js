// Tests unitaires : appels à l'API (erreurs remontées aux écrans, jeton CSRF joint aux écritures).
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { auth, login, request } from '../src/auth'

const reply = (status, body) => Promise.resolve(new Response(JSON.stringify(body), { status }))

beforeEach(() => {
  auth.user = null
  auth.csrf = ''
})
afterEach(() => vi.unstubAllGlobals())

describe('request', () => {
  it('renvoie les données d’une réponse réussie, cookie de session compris', async () => {
    const fetchMock = vi.fn(() => reply(200, { notes: [] }))
    vi.stubGlobal('fetch', fetchMock)
    expect(await request('/api/team/notes')).toEqual({ notes: [] })
    expect(fetchMock.mock.calls[0][1].credentials).toBe('same-origin')
  })

  it('lève une erreur portant le code, le statut et les erreurs par champ', async () => {
    vi.stubGlobal('fetch', () => reply(422, { error: 'validation_failed', fields: { contenu: 'Rédigez la note.' } }))
    await expect(request('/api/team/global-note-save')).rejects.toMatchObject({
      message: 'validation_failed',
      status: 422,
      fields: { contenu: 'Rédigez la note.' },
    })
  })

  it('donne un code générique si l’API ne précise pas l’erreur', async () => {
    vi.stubGlobal('fetch', () => reply(503, {}))
    await expect(request('/api/health')).rejects.toMatchObject({ message: 'unavailable', status: 503, fields: {} })
  })
})

describe('login', () => {
  it('lit d’abord la session, puis envoie le jeton CSRF et garde le nouveau', async () => {
    const fetchMock = vi
      .fn()
      .mockImplementationOnce(() => reply(200, { user: null, csrf: 'jeton-1' }))
      .mockImplementationOnce(() => reply(200, { user: { prenom: 'Chloé' }, csrf: 'jeton-2' }))
    vi.stubGlobal('fetch', fetchMock)
    await login('chloe@example.invalid', 'secret')
    const [path, options] = fetchMock.mock.calls[1]
    expect(path).toBe('/api/auth/login')
    expect(options.headers['X-CSRF-Token']).toBe('jeton-1')
    expect(auth.user.prenom).toBe('Chloé')
    expect(auth.csrf).toBe('jeton-2')
  })
})
