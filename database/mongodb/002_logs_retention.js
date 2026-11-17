// mongosh : durée de conservation des journaux de connexion (RGPD), migration idempotente.
// Les entrées CONNEXION_REUSSIE et CONNEXION_ECHOUEE contiennent une adresse IP (et l'adresse électronique saisie
// pour un échec) : MongoDB les supprime automatiquement un an après leur horodatage (index TTL partiel), durée
// recommandée par la CNIL pour les journaux de connexion (6 mois à 1 an). Les autres actions (clients, événements,
// devis) relèvent de l'historique commercial et ne sont pas concernées.
;(() => {
  'use strict'
  db.getSiblingDB('admin').auth('ecf_admin', require('fs').readFileSync('/run/secrets/mongo_root', 'utf8').trim())
  const target = db.getSiblingDB('innovevents_demo')
  if (!target.getCollectionInfos({ name: 'logs' }).length) throw new Error('Collection logs absente : créer la démonstration.')

  const name = 'ttl_logs_connexions'
  const seconds = 365 * 24 * 60 * 60
  const filter = { type_action: { $in: ['CONNEXION_REUSSIE', 'CONNEXION_ECHOUEE'] } }
  const existing = target.logs.getIndexes().find(i => i.name === name)
  if (existing) {
    const same =
      existing.expireAfterSeconds === seconds &&
      JSON.stringify(existing.key) === JSON.stringify({ horodatage: 1 }) &&
      JSON.stringify(existing.partialFilterExpression) === JSON.stringify(filter)
    if (!same) throw new Error('Index de conservation existant différent : vérifier manuellement.')
    print('Conservation des journaux de connexion déjà en place (1 an).')
    return
  }
  target.logs.createIndex({ horodatage: 1 }, { name, expireAfterSeconds: seconds, partialFilterExpression: filter })
  print('Conservation des journaux de connexion fixée à 1 an.')
})()
