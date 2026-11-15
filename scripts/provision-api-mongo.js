// mongosh : rôle MongoDB en lecture seule sur les journaux et compte de l'API qui l'utilise (écran Journaux).
// Le mot de passe arrive par l'entrée standard, jamais en argument visible.
const fs = require('fs')
const input = JSON.parse(fs.readFileSync(0, 'utf8'))
if (!/^[0-9a-f]{64}$/.test(input.password)) throw new Error('Secret incorrect')
db.getSiblingDB('admin').auth('ecf_admin', fs.readFileSync('/run/secrets/mongo_root', 'utf8').trim())
const target = db.getSiblingDB('innovevents_demo')
if (!target.getCollectionInfos({ name: 'logs' }).length) throw new Error('Démo absente')
const privileges = [{ resource: { db: 'innovevents_demo', collection: 'logs' }, actions: ['find'] }]
const role = target.getRole('ecf_logs_read', { showPrivileges: true })
if (!role) target.createRole({ role: 'ecf_logs_read', privileges, roles: [] })
else if (JSON.stringify(role.privileges) !== JSON.stringify(privileges) || role.roles.length)
  throw new Error('Rôle existant différent, vérifier manuellement')
const user = target.getUser('ecf_api_read')
if (!user)
  target.createUser({
    user: 'ecf_api_read',
    pwd: input.password,
    roles: [{ role: 'ecf_logs_read', db: 'innovevents_demo' }],
  })
else if (user.roles.length !== 1 || user.roles[0].role !== 'ecf_logs_read' || user.roles[0].db !== 'innovevents_demo')
  throw new Error('Utilisateur existant différent')
print('Compte MongoDB de lecture prêt.')
if (!/^[0-9a-f]{64}$/.test(input.audit_password)) throw new Error('Secret audit incorrect')
const auditPrivileges = [{ resource: { db: 'innovevents_demo', collection: 'logs' }, actions: ['insert'] }]
const auditRole = target.getRole('ecf_auth_insert', { showPrivileges: true })
if (!auditRole) target.createRole({ role: 'ecf_auth_insert', privileges: auditPrivileges, roles: [] })
else if (JSON.stringify(auditRole.privileges) !== JSON.stringify(auditPrivileges) || auditRole.roles.length)
  throw new Error('Rôle audit différent')
const auditUser = target.getUser('ecf_auth_log')
if (!auditUser)
  target.createUser({
    user: 'ecf_auth_log',
    pwd: input.audit_password,
    roles: [{ role: 'ecf_auth_insert', db: 'innovevents_demo' }],
  })
else if (
  auditUser.roles.length !== 1 ||
  auditUser.roles[0].role !== 'ecf_auth_insert' ||
  auditUser.roles[0].db !== 'innovevents_demo'
)
  throw new Error('Utilisateur audit différent')
