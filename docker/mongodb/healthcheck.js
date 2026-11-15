// Controle authentifie de la structure attendue, sans afficher le secret.
;(() => {
  const fs = require('fs')
  const admin = db.getSiblingDB('admin')
  admin.auth('ecf_admin', fs.readFileSync('/run/secrets/mongo_root', 'utf8').trim())
  const target = db.getSiblingDB('innovevents_test')
  const collection = target.getCollectionInfos({ name: 'logs' })
  if (collection.length !== 1 || !collection[0].options.validator) quit(1)
  const names = target.logs.getIndexes().map(index => index.name)
  for (const required of ['uq_logs_trace', 'ix_logs_date', 'ix_logs_acteur_date', 'ix_logs_action_date']) {
    if (!names.includes(required)) quit(1)
  }
})()
