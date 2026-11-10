// Utilitaire interne mongosh, appelé par prepare_demo_backup.py.
(() => {
  const fs = require('fs'), cp = require('child_process'), crypto = require('crypto');
  const action = process.env.DEMO_ACTION;
  const source = 'innovevents_demo';
  const target = process.env.DEMO_TARGET || source;
  if (target !== source && !/^innovevents_restore_[0-9a-f]{12}$/.test(target)) throw new Error('Cible non autorisée');
  const password = fs.readFileSync('/run/secrets/mongo_root', 'utf8').trim();
  db.getSiblingDB('admin').auth('ecf_admin', password);
  const selected = db.getSiblingDB(target);
  const exists = db.getSiblingDB('admin').runCommand({listDatabases: 1, nameOnly: true}).databases.some(x => x.name === target);
  if (action === 'exists') { print(JSON.stringify({exists})); return; }
  if (action === 'seed') {
    if (exists || target !== source) throw new Error('Base démo existante : aucun remplacement');
    db = selected;
    load('/tmp/innovevents-logs-schema.js');
    const details = [{type_action:'CREATION_CLIENT',details:{id_client:'1',nom_client:'Entreprise fictive Horizon'}},
      ...['1','2','3'].map(id => ({type_action:'CREATION_EVENEMENT',details:{id_evenement:id,nom_evenement:'Événement fictif de démonstration '+id}}))];
    selected.logs.insertMany(details.map(x => ({...x,horodatage:new Date(),id_utilisateur:'1',id_trace:crypto.randomUUID(),version_schema:NumberInt(1)})));
    print(JSON.stringify({logs:selected.logs.countDocuments()})); return;
  }
  if (action === 'snapshot') {
    if (!exists) throw new Error('Base absente');
    const info = selected.getCollectionInfos({name:'logs'})[0];
    print(EJSON.stringify({options:info.options,indexes:selected.logs.getIndexes().map(x => {delete x.ns; return x;}).sort((a,b)=>a.name.localeCompare(b.name)),
      documents:selected.logs.find().sort({_id:1}).toArray()}, {relaxed:false})); return;
  }
  if (!['dump','restore'].includes(action)) throw new Error('Action inconnue');
  if (action === 'restore' && (exists || target === source)) throw new Error('Restauration uniquement vers une nouvelle base');
  const folder = fs.mkdtempSync('/tmp/innovevents-tools-');
  const config = folder+'/config.yml';
  fs.writeFileSync(config,'password: '+JSON.stringify(password)+'\n',{mode:0o600});
  const archive = '/tmp/innovevents-demo.archive.gz';
  try {
    const common = ['--config='+config,'--uri=mongodb://ecf_admin@127.0.0.1:27017/?authSource=admin','--archive='+archive,'--gzip'];
    const args = action === 'dump' ? [...common,'--db='+source] : [...common,'--nsInclude='+source+'.*','--nsFrom='+source+'.*','--nsTo='+target+'.*','--stopOnError'];
    cp.execFileSync(action === 'dump' ? 'mongodump' : 'mongorestore',args,{stdio:['ignore','pipe','pipe']});
    print(JSON.stringify({action,cible:target,reussi:true}));
  } catch (_) { throw new Error('Outil MongoDB en échec ; aucune réussite déclarée.'); }
  finally { fs.unlinkSync(config); fs.rmdirSync(folder); }
})();
