// Mise en forme des événements publics (heure de Paris fournie par l'API, format AAAA-MM-JJTHH:MM).
const dateFormat = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'long', timeZone: 'UTC' })
const timeFormat = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: 'UTC' })

// Les dates reçues sont déjà en heure de Paris : on les lit comme UTC pour ne pas les décaler une seconde fois.
const parse = value => new Date(value + ':00Z')

/** Période lisible : « 12 mars 2026, de 09:00 à 17:00 » ou « Du … au … » sur plusieurs jours. */
export function period(start, end) {
  const a = parse(start),
    b = parse(end)
  if (start.slice(0, 10) === end.slice(0, 10))
    return `${dateFormat.format(a)}, de ${timeFormat.format(a)} à ${timeFormat.format(b)}`
  return `Du ${dateFormat.format(a)} à ${timeFormat.format(a)} au ${dateFormat.format(b)} à ${timeFormat.format(b)}`
}

// Statuts affichés au public.
export const statusLabels = { accepte: 'À venir', en_cours: 'En préparation', termine: 'Réalisé', annule: 'Annulé' }
