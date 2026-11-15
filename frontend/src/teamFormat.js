// Libellés et formats partagés par les écrans de l'espace employé.
export const eventStatuses = {
  brouillon: 'Brouillon',
  accepte: 'Accepté',
  en_cours: 'En cours',
  termine: 'Terminé',
  annule: 'Annulé',
}

export const taskStatuses = { a_faire: 'À faire', en_cours: 'En cours', termine: 'Terminé' }

/** Date locale « AAAA-MM-JJTHH:MM » (heure de Paris, déjà convertie par l'API). */
export function localDate(v, withTime = true) {
  const m = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})$/.exec(v || '')
  if (!m) return 'Date à définir'
  return withTime ? `${m[3]}/${m[2]}/${m[1]} à ${m[4]} h ${m[5]}` : `${m[3]}/${m[2]}/${m[1]}`
}

/** Horodatage UTC de la base (« AAAA-MM-JJ HH:MM:SS.ffffff ») affiché en heure de Paris. */
export function stamp(v) {
  if (!v) return ''
  return new Date(v.replace(' ', 'T').slice(0, 19) + 'Z').toLocaleString('fr-FR', {
    timeZone: 'Europe/Paris',
    dateStyle: 'short',
    timeStyle: 'short',
  })
}
