"""Devis : calcul, enregistrement transactionnel et droits."""
from pathlib import Path
import http.cookiejar,json,os,secrets,subprocess,time,urllib.request,urllib.error
ROOT=Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:5173';COMPOSE=[os.environ.get('DOCKER_BIN','docker'),'compose','-f','compose.yaml','-f','compose.app.yaml'];results={};revoked=False
email='quote-draft-'+secrets.token_hex(8)+'@example.invalid'
def run(args,input=None):
 return subprocess.run(COMPOSE+args,input=input,encoding='utf-8',capture_output=True,cwd=ROOT,timeout=90)
def sql(q):
 r=run(['exec','-T','mysql','sh','/opt/innovevents/client.sh','--default-character-set=utf8mb4','--batch','--skip-column-names','innovevents_demo'],q)
 if r.returncode:raise RuntimeError('SQL de test refusé')
 return r.stdout.strip()
class Browser:
 def __init__(self):
  self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf='';self.call('/api/auth/session')
 def call(self,path,data=None,csrf=True):
  req=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers={'Origin':BASE,'Content-Type':'application/json','X-CSRF-Token':self.csrf if csrf else ''})
  try:
   with self.client.open(req,timeout=30) as r:s,d=r.status,json.loads(r.read())
  except urllib.error.HTTPError as e:s,d=e.code,json.loads(e.read())
  if 'csrf' in d:self.csrf=d['csrf']
  return s,d
 def login(self,address):return self.call('/api/auth/login',{'email':address,'password':(ROOT/'.secrets/demo-password.txt').read_text().strip()})
try:
 sql(f"INSERT INTO clients(entreprise,contact_nom,contact_prenom,email,telephone) VALUES('Test devis','Nom','Prénom','{email}','0600000000');")
 cid=sql(f"SELECT id FROM clients WHERE email='{email}';")
 sql(f"INSERT INTO evenements(client_id,nom) VALUES({cid},'Devis test'),({cid},'Devis ordinateur'),({cid},'Devis téléphone');")
 ids=sql(f"SELECT id FROM evenements WHERE client_id={cid} ORDER BY id;").splitlines();eid=ids[0]
 admin=Browser();client=Browser();employee=Browser();anon=Browser()
 for b,address in [(admin,'chloe@example.invalid'),(client,'alice@example.invalid'),(employee,'jose@example.invalid')]:assert b.login(address)[0]==200
 endpoint='/api/admin/quote-draft-save';payload={'event_id':eid,'revision':0,'taux_tva':'10','prestations':[{'libelle':'A','montant_ht':'0.05'},{'libelle':'B','montant_ht':'0.05'}]}
 results['anonymous_denied']=anon.call(endpoint,payload)[0]==401
 results['client_denied']=client.call(endpoint,payload)[0]==403
 results['employee_denied']=employee.call(endpoint,payload)[0]==403
 results['csrf_required']=admin.call(endpoint,payload,False)[0]==403
 results['missing_rate']=admin.call(endpoint,{**payload,'taux_tva':''})[0]==422
 results['rate_out_of_range']=admin.call(endpoint,{**payload,'taux_tva':'100.01'})[0]==422
 results['empty_lines']=admin.call(endpoint,{**payload,'prestations':[]})[0]==422
 results['invalid_line']=admin.call(endpoint,{**payload,'prestations':[{'libelle':'','montant_ht':'-1'}]})[0]==422
 code,d=admin.call(endpoint,{**payload,'total_ttc':'0','statut':'accepte'});assert code==200,d
 results['exact_server_totals']=d['total_ht']=='0.10' and d['total_tva']=='0.01' and d['total_ttc']=='0.11'
 results['replay_same_result']=admin.call(endpoint,payload)==(200,d)
 results['creation_unique']=sql(f"SELECT COUNT(*) FROM devis WHERE evenement_id={eid};")=='1'
 results['stale_conflict']=admin.call(endpoint,{**payload,'taux_tva':'20'})[0]==409
 q=admin.call('/api/admin/quote-draft?event_id='+eid)[1]['quote'];results['read_saved']=q['revision']==1 and q['statut']=='brouillon' and len(q['prestations'])==2
 results['read_denied']=client.call('/api/admin/quote-draft?event_id='+eid)[0]==403
 p2={**payload,'revision':1,'taux_tva':'20','prestations':[{'libelle':'Traiteur','montant_ht':'1250,50'}]}
 code,d2=admin.call(endpoint,p2);results['replace_lines']=code==200 and d2['total_ttc']=='1500.60' and d2['revision']==2
 results['retry_edit']=admin.call(endpoint,p2)==(200,d2)
 vid=sql(f"SELECT version_courante_id FROM devis WHERE evenement_id={eid};")
 results['old_lines_removed']=sql(f"SELECT COUNT(*) FROM prestations_devis WHERE version_id={vid};")=='1'
 # Permission failure after line deletion must roll the entire transaction back.
 revoked=True;sql("REVOKE INSERT (version_id,ordre,libelle,montant_ht) ON innovevents_demo.prestations_devis FROM 'ecf_quote_drafts'@'%';")
 results['failure_reported']=admin.call(endpoint,{**p2,'revision':2,'taux_tva':'5'})[0]==503
 q=admin.call('/api/admin/quote-draft?event_id='+eid)[1]['quote'];results['transaction_rolls_back']=q['revision']==2 and q['total_ttc']=='1500.60' and len(q['prestations'])==1
 sql("GRANT INSERT (version_id,ordre,libelle,montant_ht) ON innovevents_demo.prestations_devis TO 'ecf_quote_drafts'@'%';");revoked=False
 sql(f"UPDATE versions_devis SET client_snapshot=JSON_OBJECT(),evenement_snapshot=JSON_OBJECT(),figee_le=UTC_TIMESTAMP() WHERE id={vid};")
 results['frozen_quote_refused']=admin.call(endpoint,{**p2,'revision':2})[0]==409
 sql(f"UPDATE evenements SET statut='annule' WHERE id={eid};")
 results['cancelled_event_refused']=admin.call(endpoint,{**p2,'revision':2})[0]==409
 env={**os.environ,'QUOTE_TEST_IDS':','.join(ids[1:])}
 b=subprocess.run([os.environ.get('NODE_BIN','node'),'tests/app/quote-drafts-browser.cjs'],cwd=ROOT,env=env,encoding='utf-8',capture_output=True);print(b.stdout);print(b.stderr if b.returncode else '');results['browser']=b.returncode==0
finally:
 if revoked:sql("GRANT INSERT (version_id,ordre,libelle,montant_ht) ON innovevents_demo.prestations_devis TO 'ecf_quote_drafts'@'%';")
 if 'cid' in locals():
  sql(f"DELETE p FROM prestations_devis p JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id={cid}; UPDATE devis d JOIN evenements e ON e.id=d.evenement_id SET d.version_courante_id=NULL WHERE e.client_id={cid}; DELETE v FROM versions_devis v JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id={cid}; DELETE d FROM devis d JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id={cid}; DELETE FROM evenements WHERE client_id={cid}; DELETE FROM clients WHERE id={cid};")
 (ROOT/'tmp/quote-drafts-tests.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print(json.dumps(results,indent=2));raise SystemExit(0 if len(results)==22 and all(results.values()) else 1)
