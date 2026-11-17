// Règles de navigation liées à la session : accès aux pages privées et page de retour après la connexion.

// Pages de retour acceptées (liste fermée) : un lien piégé ne peut pas rediriger vers un autre site.
const destinations = [
  '/espace',
  '/journaux',
  '/prospects',
  '/parametres-demandes',
  '/mot-de-passe',
  '/devis',
  '/espace/informations',
  '/moderation-avis',
  '/comptes',
]

/**
 * Page où aller après la connexion : celle demandée avant de se connecter si elle est dans la liste
 * ou correspond à une fiche (identifiant numérique), sinon l'espace personnel.
 */
export function returnTarget(retour) {
  return typeof retour === 'string' &&
    (destinations.includes(retour) ||
      /^\/prospects\/[1-9][0-9]{0,17}(\/(convertir|evenement))?$/.test(retour) ||
      /^\/(clients|evenements)\/[1-9][0-9]{0,17}(\/devis)?$/.test(retour) ||
      /^\/equipe\/(clients|evenements)(\/[1-9][0-9]{0,17})?$/.test(retour))
    ? retour
    : '/espace'
}

/**
 * Redirection éventuelle avant d'afficher une page. Sans compte, une page privée renvoie vers la connexion
 * avec retour prévu ; un mot de passe à changer bloque les autres pages privées. Renvoie undefined si l'accès est libre.
 */
export function accessRedirect(to, user) {
  if (!to.meta?.private) return undefined
  if (!user) return { path: '/connexion', query: { retour: to.path } }
  if (user.password_change_required && to.path !== '/mot-de-passe') return '/mot-de-passe'
  return undefined
}
