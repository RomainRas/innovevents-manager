// Tests unitaires : mise en forme des dates et libellés affichés (pages publiques et espace employé).
import { describe, expect, it } from 'vitest'
import { period, statusLabels } from '../src/publicFormat'
import { eventStatuses, localDate, stamp, taskStatuses } from '../src/teamFormat'

describe('period (pages publiques)', () => {
  it('affiche un événement sur une journée', () => {
    expect(period('2026-03-12T09:00', '2026-03-12T17:30')).toBe('12 mars 2026, de 09:00 à 17:30')
  })

  it('affiche un événement sur plusieurs jours', () => {
    expect(period('2026-12-31T20:00', '2027-01-01T02:00')).toBe('Du 31 décembre 2026 à 20:00 au 1 janvier 2027 à 02:00')
  })

  it('n’affiche aucun statut de travail interne au public', () => {
    expect(Object.keys(statusLabels)).not.toContain('brouillon')
  })
})

describe('localDate (espace employé)', () => {
  it('affiche la date et l’heure de Paris reçues de l’API', () => {
    expect(localDate('2027-01-28T18:30')).toBe('28/01/2027 à 18 h 30')
    expect(localDate('2027-01-28T18:30', false)).toBe('28/01/2027')
  })

  it('signale une date absente ou mal formée', () => {
    expect(localDate('')).toBe('Date à définir')
    expect(localDate('28/01/2027')).toBe('Date à définir')
  })
})

describe('stamp (horodatage de la base)', () => {
  it('convertit l’heure UTC de la base en heure de Paris, été comme hiver', () => {
    expect(stamp('2026-11-17 09:19:00.123456')).toBe('17/11/2026 10:19')
    expect(stamp('2026-07-01 08:00:00.000000')).toBe('01/07/2026 10:00')
  })

  it('n’affiche rien sans date', () => {
    expect(stamp(null)).toBe('')
  })
})

describe('libellés', () => {
  it('couvrent tous les statuts d’événement et de tâche de l’énoncé', () => {
    expect(Object.keys(eventStatuses)).toEqual(['brouillon', 'accepte', 'en_cours', 'termine', 'annule'])
    expect(Object.values(taskStatuses)).toEqual(['À faire', 'En cours', 'Terminé'])
  })
})
