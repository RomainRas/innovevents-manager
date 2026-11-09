// mongosh : initialisation unique dans une base vide innovevents_*.
// Ne supprime ni ne modifie une collection existante. Pas de durée TTL inventée.
(() => {
  'use strict';
  if (!/^innovevents_[a-z0-9_]+$/.test(db.getName())) {
    throw new Error('Sélectionner explicitement une base innovevents_* dédiée.');
  }
  if (db.getCollectionInfos({name: 'logs'}).length !== 0) {
    throw new Error('La collection logs existe : utiliser une migration revue, pas une réinitialisation.');
  }

  const identifier = {bsonType: 'string', pattern: '^[1-9][0-9]{0,19}$'};
  const text = maxLength => ({bsonType: 'string', minLength: 1, maxLength});
  const eventStatus = {enum: ['brouillon', 'accepte', 'en_cours', 'termine', 'annule']};
  const actions = {
    CONNEXION_REUSSIE: {ip: text(45)},
    CONNEXION_ECHOUEE: {email_utilise: text(254), ip: text(45)},
    CREATION_CLIENT: {id_client: identifier, nom_client: text(200)},
    MODIFICATION_CLIENT: {id_client: identifier},
    SUPPRESSION_CLIENT: {id_client: identifier, nom_client: text(200)},
    CREATION_EVENEMENT: {id_evenement: identifier, nom_evenement: text(200)},
    CHANGEMENT_STATUT_EVENEMENT: {
      id_evenement: identifier, ancien_statut: eventStatus, nouveau_statut: eventStatus
    },
    GENERATION_PDF_DEVIS: {id_evenement: identifier}
  };
  const schema = {
    bsonType: 'object', additionalProperties: false,
    required: ['horodatage', 'type_action', 'id_utilisateur', 'details', 'id_trace', 'version_schema'],
    properties: {
      _id: {bsonType: 'objectId'},
      horodatage: {bsonType: 'date'},
      type_action: {enum: Object.keys(actions)},
      id_utilisateur: {bsonType: ['string', 'null']},
      details: {bsonType: 'object'},
      id_trace: {bsonType: 'string', pattern: '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$'},
      version_schema: {bsonType: ['int', 'long'], enum: [1]}
    },
    oneOf: Object.entries(actions).map(([action, properties]) => ({
      properties: {
        type_action: {enum: [action]},
        id_utilisateur: action === 'CONNEXION_ECHOUEE'
          ? {oneOf: [identifier, {bsonType: 'null'}]} : identifier,
        details: {
          bsonType: 'object', additionalProperties: false,
          required: Object.keys(properties), properties
        }
      }
    }))
  };
  const result = db.createCollection('logs', {
    validator: {$jsonSchema: schema}, validationLevel: 'strict', validationAction: 'error'
  });
  if (result.ok !== 1) throw new Error('Création de logs non confirmée.');
  db.logs.createIndex({id_trace: 1}, {unique: true, name: 'uq_logs_trace'});
  db.logs.createIndex({horodatage: -1}, {name: 'ix_logs_date'});
  db.logs.createIndex({id_utilisateur: 1, horodatage: -1}, {name: 'ix_logs_acteur_date'});
  db.logs.createIndex({type_action: 1, horodatage: -1}, {name: 'ix_logs_action_date'});
  print('Collection logs et quatre index créés. Aucun jeu de données inséré.');
})();
