// Tests unitaires : accès aux pages privées et page de retour après la connexion (protection contre la redirection ouverte).
import { describe, expect, it } from 'vitest'
import { accessRedirect, returnTarget } from '../src/navigation'

describe('returnTarget', () => {
  it('accepte les pages de la liste fermée', () => {
    expect(returnTarget('/journaux')).toBe('/journaux')
    expect(returnTarget('/espace/informations')).toBe('/espace/informations')
  })

  it('accepte une fiche désignée par un identifiant numérique', () => {
    expect(returnTarget('/prospects/12/convertir')).toBe('/prospects/12/convertir')
    expect(returnTarget('/evenements/7/devis')).toBe('/evenements/7/devis')
    expect(returnTarget('/equipe/clients/3')).toBe('/equipe/clients/3')
  })

  it('refuse un autre site, un chemin inconnu ou un identifiant invalide', () => {
    for (const retour of ['https://exemple.invalid', '//exemple.invalid/espace', '/inconnu', '/evenements/0', '/evenements/abc', '/journaux?x=1'])
      expect(returnTarget(retour)).toBe('/espace')
  })

  it('renvoie vers l’espace personnel sans retour demandé', () => {
    expect(returnTarget(undefined)).toBe('/espace')
    expect(returnTarget(['/journaux'])).toBe('/espace')
  })
})

describe('accessRedirect', () => {
  const privatePage = { path: '/journaux', meta: { private: true } }

  it('laisse passer une page publique, même sans compte', () => {
    expect(accessRedirect({ path: '/avis', meta: {} }, null)).toBeUndefined()
  })

  it('envoie vers la connexion, avec la page demandée en retour', () => {
    expect(accessRedirect(privatePage, null)).toEqual({ path: '/connexion', query: { retour: '/journaux' } })
  })

  it('impose le changement de mot de passe avant les autres pages privées', () => {
    const user = { role: 'client', password_change_required: true }
    expect(accessRedirect(privatePage, user)).toBe('/mot-de-passe')
    expect(accessRedirect({ path: '/mot-de-passe', meta: { private: true } }, user)).toBeUndefined()
  })

  it('laisse passer un utilisateur connecté', () => {
    expect(accessRedirect(privatePage, { role: 'administrateur' })).toBeUndefined()
  })
})
