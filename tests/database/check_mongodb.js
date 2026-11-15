// mongosh, base locale de test uniquement ; nettoyage limité aux identifiants créés ici.
;(() => {
  const fs = require('fs')
  const crypto = require('crypto')
  db.getSiblingDB('admin').auth('ecf_admin', fs.readFileSync('/run/secrets/mongo_root', 'utf8').trim())
  const target = db.getSiblingDB('innovevents_test')
  const ids = []
  const results = []
  const actions = {
    CONNEXION_REUSSIE: { ip: '192.0.2.1' },
    CONNEXION_ECHOUEE: { email_utilise: 'test@example.invalid', ip: '192.0.2.1' },
    CREATION_CLIENT: { id_client: '1', nom_client: 'Client fictif' },
    MODIFICATION_CLIENT: { id_client: '1' },
    SUPPRESSION_CLIENT: { id_client: '1', nom_client: 'Client fictif' },
    CREATION_EVENEMENT: { id_evenement: '1', nom_evenement: 'Test fictif' },
    CHANGEMENT_STATUT_EVENEMENT: { id_evenement: '1', ancien_statut: 'brouillon', nouveau_statut: 'accepte' },
    GENERATION_PDF_DEVIS: { id_evenement: '1' },
  }
  function sample(action = 'CONNEXION_REUSSIE') {
    const id = new ObjectId()
    ids.push(id)
    return {
      _id: id,
      horodatage: new Date(),
      type_action: action,
      id_utilisateur: action === 'CONNEXION_ECHOUEE' ? null : '1',
      details: { ...actions[action] },
      id_trace: crypto.randomUUID(),
      version_schema: NumberInt(1),
    }
  }
  function check(name, document, expected = null) {
    let actual = null
    try {
      target.logs.insertOne(document)
    } catch (error) {
      actual = error.code || 'inconnu'
    }
    results.push({ cas: name, reussi: actual === expected, code_attendu: expected, code_recu: actual })
  }
  try {
    for (const action of Object.keys(actions)) check(action, sample(action))
    let value = sample()
    value.type_action = 'INCONNU'
    check('action_inconnue', value, 121)
    value = sample()
    value.mot_de_passe = 'valeur-fictive-interdite'
    check('champ_sensible_interdit', value, 121)
    value = sample()
    value.horodatage = '2026-11-09'
    check('date_bson_obligatoire', value, 121)
    value = sample()
    value.id_utilisateur = null
    check('acteur_requis', value, 121)
    value = sample()
    value.details = {}
    check('details_requis', value, 121)
    value = sample()
    value.version_schema = NumberInt(2)
    check('version_inconnue', value, 121)
    value = sample()
    check('trace_initiale', value)
    const duplicate = sample()
    duplicate.id_trace = value.id_trace
    check('trace_unique', duplicate, 11000)
  } finally {
    target.logs.deleteMany({ _id: { $in: ids } })
  }
  results.push({ cas: 'nettoyage_cible', reussi: target.logs.countDocuments({ _id: { $in: ids } }) === 0 })
  print(JSON.stringify({ moteur: db.version(), tests: results }, null, 2))
  quit(results.every(r => r.reussi) ? 0 : 1)
})()
