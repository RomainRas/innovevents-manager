// Tests unitaires du composant GlobalNotes : affichage selon le rôle, contrôle avant envoi, écriture avec jeton CSRF,
// texte des notes affiché tel quel (jamais interprété comme du HTML).
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick } from 'vue'
import { createMemoryHistory, createRouter } from 'vue-router'
import GlobalNotes from '../src/GlobalNotes.vue'
import { auth } from '../src/auth'

const note = (id, contenu, modifiable) => ({
  id,
  contenu,
  cree_le: '2026-11-17 09:19:00.000000',
  modifiee: false,
  auteur: 'Chloé D.',
  modifiable,
})
const reply = (status, body) => Promise.resolve(new Response(JSON.stringify(body), { status }))

// Laisse les promesses (appels simulés) et le rendu se terminer.
const settle = async () => {
  for (let i = 0; i < 5; i++) {
    await new Promise(r => setTimeout(r))
    await nextTick()
  }
}

let app, root

async function mount(admin, fetchMock) {
  vi.stubGlobal('fetch', fetchMock)
  const router = createRouter({ history: createMemoryHistory(), routes: [{ path: '/:p(.*)*', component: { render: () => null } }] })
  root = document.createElement('div')
  document.body.append(root)
  app = createApp(GlobalNotes, { admin }).use(router)
  app.mount(root)
  await settle()
}

beforeEach(() => {
  auth.csrf = 'jeton-test'
})
afterEach(() => {
  app?.unmount()
  root?.remove()
  vi.unstubAllGlobals()
})

describe('GlobalNotes', () => {
  it('montre les notes à un employé, sans formulaire ni bouton', async () => {
    await mount(false, vi.fn(() => reply(200, { notes: [note(1, 'Fermeture vendredi.', false)] })))
    expect(root.textContent).toContain('Fermeture vendredi.')
    expect(root.textContent).toContain('Chloé D. — 17/11/2026 10:19')
    expect(root.querySelector('form')).toBeNull()
    expect(root.querySelectorAll('.notes-box button')).toHaveLength(0)
  })

  it('donne à l’administratrice le formulaire et les boutons Modifier et Supprimer', async () => {
    await mount(true, vi.fn(() => reply(200, { notes: [note(1, 'Fermeture vendredi.', true)] })))
    expect(root.querySelector('#global-note-new')).not.toBeNull()
    const labels = [...root.querySelectorAll('.notes-box button')].map(b => b.textContent.trim())
    expect(labels).toEqual(['Modifier', 'Supprimer…'])
  })

  it('affiche le contenu d’une note comme du texte, sans l’interpréter', async () => {
    const piege = '<img src=x onerror="alert(1)">'
    await mount(false, vi.fn(() => reply(200, { notes: [note(1, piege, false)] })))
    expect(root.querySelector('.notes-box img')).toBeNull()
    expect(root.textContent).toContain(piege)
  })

  it('signale une note vide sans appeler l’API', async () => {
    const fetchMock = vi.fn(() => reply(200, { notes: [] }))
    await mount(true, fetchMock)
    root.querySelector('form').dispatchEvent(new Event('submit'))
    await settle()
    expect(root.querySelector('#global-note-new-error').textContent).toContain('Rédigez la note')
    expect(root.querySelector('#global-note-new').getAttribute('aria-invalid')).toBe('true')
    expect(fetchMock).toHaveBeenCalledTimes(1) // seulement le chargement initial
  })

  it('publie une note avec le jeton CSRF et affiche la liste renvoyée', async () => {
    const fetchMock = vi
      .fn()
      .mockImplementationOnce(() => reply(200, { notes: [] }))
      .mockImplementationOnce(() => reply(200, { notes: [note(9, 'Réunion lundi 9 h.', true)] }))
    await mount(true, fetchMock)
    const textarea = root.querySelector('#global-note-new')
    textarea.value = 'Réunion lundi 9 h.'
    textarea.dispatchEvent(new Event('input'))
    root.querySelector('form').dispatchEvent(new Event('submit'))
    await settle()
    const [path, options] = fetchMock.mock.calls[1]
    expect(path).toBe('/api/team/global-note-save')
    expect(options.headers['X-CSRF-Token']).toBe('jeton-test')
    expect(JSON.parse(options.body)).toEqual({ contenu: 'Réunion lundi 9 h.' })
    expect(root.textContent).toContain('Note publiée pour toute l’équipe.')
    expect(root.textContent).toContain('Réunion lundi 9 h.')
    expect(textarea.value).toBe('')
  })

  it('garde le texte et explique un refus du serveur', async () => {
    const fetchMock = vi
      .fn()
      .mockImplementationOnce(() => reply(200, { notes: [] }))
      .mockImplementationOnce(() => reply(403, { error: 'forbidden' }))
    await mount(true, fetchMock)
    const textarea = root.querySelector('#global-note-new')
    textarea.value = 'Texte à conserver'
    textarea.dispatchEvent(new Event('input'))
    root.querySelector('form').dispatchEvent(new Event('submit'))
    await settle()
    expect(root.querySelector('#global-error').textContent).toContain('Seule l’administratrice')
    expect(textarea.value).toBe('Texte à conserver')
  })
})
