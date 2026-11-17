// Contrôle d'une note avant envoi, identique à celui de l'API : texte non vide, 5 000 caractères au plus,
// sans caractère de contrôle (les retours à la ligne et tabulations restent permis).
// Renvoie le message d'erreur à afficher, ou une chaîne vide si la note est valide.
export const NOTE_MAX = 5000

export function noteError(text) {
  const value = typeof text === 'string' ? text.trim() : ''
  if (!value) return 'Rédigez la note avant de l’enregistrer.'
  if ([...value].length > NOTE_MAX) return `La note dépasse ${NOTE_MAX.toLocaleString('fr-FR')} caractères.`
  // eslint-disable-next-line no-control-regex
  if (/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/.test(value)) return 'La note contient un caractère non autorisé.'
  return ''
}
