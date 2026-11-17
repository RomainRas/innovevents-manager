// Tests unitaires : contrôle d'une note avant envoi, aligné sur celui de l'API.
import { describe, expect, it } from 'vitest'
import { NOTE_MAX, noteError } from '../src/noteRules'

describe('noteError', () => {
  it('accepte une note ordinaire, sur plusieurs lignes', () => {
    expect(noteError('Prévenir le traiteur.\nRappeler le client demain.')).toBe('')
  })

  it('refuse une note vide ou faite d’espaces', () => {
    expect(noteError('')).not.toBe('')
    expect(noteError('   \n ')).not.toBe('')
    expect(noteError(undefined)).not.toBe('')
  })

  it('compte les caractères accentués comme un seul caractère', () => {
    expect(noteError('é'.repeat(NOTE_MAX))).toBe('')
    expect(noteError('é'.repeat(NOTE_MAX + 1))).toContain('5')
  })

  it('refuse les caractères de contrôle', () => {
    expect(noteError('Bip\u0007')).toContain('non autorisé')
  })

  it('laisse le code HTML comme du texte ordinaire (il sera échappé à l’affichage)', () => {
    expect(noteError('<script>alert(1)</script>')).toBe('')
  })
})
