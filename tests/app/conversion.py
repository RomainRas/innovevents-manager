"""Conversion, reprise du journal MongoDB et nettoyage ciblé des fiches fictives."""
from pathlib import Path
import http.cookiejar,json,os,secrets,subprocess,time,urllib.request,urllib.error
ROOT=Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:5173';DOCKER=os.environ.get('DOCKER_BIN','docker');COMPOSE=[DOCKER,'compose','-f','compose.yaml','-f','compose.app.yaml']
email='conversion-test-'+secrets.token_hex(8)+'@example.invalid';results={};ids=[];revoked=False;mongo_stopped=False
def run(args,input=None):
 return subprocess.run(COMPOSE+args,input=input,encoding='utf-8',capture_output=True,cwd=ROOT,timeout=90)
def sql(query):
 r=run(['exec','-T','mysql','sh','/opt/innovevents/client.sh','--default-character-set=utf8mb4','--batch','--skip-column-names','innovevents_demo'],query)
 if r.returncode:raise RuntimeError('Commande SQL de test échouée')
 return r.stdout.strip()
class Browser:
 def __init__(self):
  self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf='';self.csrf=self.call('/api/auth/session')[1]['csrf']
 def call(self,path,data=None,csrf=True):
  req=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers={'Origin':BASE,'Content-Type':'application/json','X-CSRF-Token':self.csrf if csrf else ''})
  try:
   with self.client.open(req,timeout=30) as r:s,d=r.status,json.loads(r.read())
  except urllib.error.HTTPError as e:s,d=e.code,json.loads(e.read())
  if 'csrf' in d:self.csrf=d['csrf']
  return s,d
 def login(self,address):return self.call('/api/auth/login',{'email':address,'password':(ROOT/'.secrets/demo-password.txt').read_text().strip()})
def wait_for(fn,seconds=85):
 end=time.monotonic()+seconds
 while time.monotonic()<end:
  if fn():return True
  time.sleep(1)
 return False
def trace_for(cid):return sql(f"SELECT id_trace FROM journal_a_emettre WHERE type_action='CREATION_CLIENT' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_client'))='{cid}';")
def delivered(trace):return sql(f"SELECT etat FROM journal_a_emettre WHERE id_trace='{trace}';")=='livre'
def inspect(trace):
 r=run(['exec','-T','api','php','tests/trace-inspect.php'],trace)
 if r.returncode:raise RuntimeError('Lecture de la trace échouée')
 return json.loads(r.stdout)
try:
 values=','.join(f"('Test conversion {i}','Nom','Prénom','{email}','0600000000','Lyon','Séminaire','2031-07-01',20,'Besoin fictif')" for i in range(6))
 sql('INSERT INTO prospects(entreprise,nom,prenom,email,telephone,lieu,type_evenement,date_souhaitee,participants_estimes,besoin) VALUES '+values+';')
 ids=sql(f"SELECT id FROM prospects WHERE email='{email}' ORDER BY id;").splitlines()
 admin=Browser();client=Browser();employee=Browser();anon=Browser()
 assert admin.login('chloe@example.invalid')[0]==200
 assert client.login('alice@example.invalid')[0]==200
 assert employee.login('jose@example.invalid')[0]==200
 p=dict(id=ids[0],entreprise='Entreprise corrigée',contact_nom='Nom corrigé',contact_prenom='Prénom',email=email,telephone='0611223344')
 endpoint='/api/admin/prospect-conversion'
 results['anonymous_denied']=anon.call(endpoint,p)[0]==401
 results['client_denied']=client.call(endpoint,p)[0]==403
 results['employee_denied']=employee.call(endpoint,p)[0]==403
 results['csrf_required']=admin.call(endpoint,p,csrf=False)[0]==403
 results['mandatory_coordinates']=all(admin.call(endpoint,{**p,k:''})[0]==422 for k in ['entreprise','contact_nom','contact_prenom','email','telephone'])
 results['invalid_email']=admin.call(endpoint,{**p,'email':'bad@'})[0]==422
 code,data=admin.call(endpoint,{**p,'utilisateur_id':1,'statut':'echoue'})
 results['conversion_success']=code==200 and data['status']=='converti';cid=data['client_id']
 results['prospect_linked']=sql(f"SELECT statut,client_id FROM prospects WHERE id={ids[0]};")=='converti\t'+cid
 details=admin.call('/api/admin/client?id='+cid)[1]['client']
 results['edited_coordinates_saved']=details['entreprise']==p['entreprise'] and details['contact_nom']==p['contact_nom'] and details['telephone']==p['telephone']
 results['no_automatic_account_link']=sql(f"SELECT utilisateur_id IS NULL FROM clients WHERE id={cid};")=='1'
 results['prospect_original_preserved']=sql(f"SELECT entreprise FROM prospects WHERE id={ids[0]};")=='Test conversion 0'
 results['repeat_returns_same_client']=admin.call(endpoint,p)==(200,data)
 results['changed_retry_conflicts']=admin.call(endpoint,{**p,'entreprise':'Autre entreprise'})[0]==409
 results['single_client_after_retry']=sql(f"SELECT COUNT(*) FROM clients WHERE email='{email}';")=='1'
 results['client_detail_forbidden']=client.call('/api/admin/client?id='+cid)[0]==403
 sql(f"UPDATE prospects SET statut='echoue',message_echec='Test',qualifie_le=UTC_TIMESTAMP() WHERE id={ids[1]};")
 results['failed_prospect_refused']=admin.call(endpoint,{**p,'id':ids[1]})[0]==409
 trace=trace_for(cid);assert len(trace)==36
 results['journal_delivered']=wait_for(lambda:delivered(trace),20)
 log=inspect(trace)
 results['mongo_trace_contract']=log['count']==1 and log['type']=='CREATION_CLIENT' and log['details']=={'id_client':cid,'nom_client':p['entreprise']} and log['actor'] is not None
 # Simuler MongoDB validé mais accusé de livraison SQL perdu.
 sql(f"UPDATE journal_a_emettre SET etat='en_attente',livre_le=NULL,prochain_essai_le=NULL WHERE id_trace='{trace}';")
 results['mongo_replay_deduplicated']=wait_for(lambda:delivered(trace),20) and inspect(trace)['count']==1
 revoked=True;sql("REVOKE INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre FROM 'ecf_conversion'@'%';")
 results['journal_enqueue_failure_reported']=admin.call(endpoint,{**p,'id':ids[2]})[0]==503
 results['journal_enqueue_rolls_back_conversion']=sql(f"SELECT statut,client_id IS NULL FROM prospects WHERE id={ids[2]};")=='a_contacter\t1' and sql(f"SELECT COUNT(*) FROM clients WHERE email='{email}';")=='1'
 sql("GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_conversion'@'%';");revoked=False
 mongo_stopped=True;assert run(['stop','mongodb']).returncode==0
 code,data=admin.call(endpoint,{**p,'id':ids[3]})
 results['conversion_survives_mongo_outage']=code==200;outage_trace=trace_for(data['client_id'])
 results['journal_waits_durably']=wait_for(lambda:sql(f"SELECT etat,tentatives>0 FROM journal_a_emettre WHERE id_trace='{outage_trace}';")=='en_attente\t1',20)
 assert run(['start','mongodb']).returncode==0;mongo_stopped=False
 results['journal_recovers_after_outage']=wait_for(lambda:delivered(outage_trace)) and inspect(outage_trace)['count']==1
 env={**os.environ,'CONVERSION_TEST_IDS':','.join(ids[-2:]),'CONVERSION_TEST_EMAIL':email}
 browser=subprocess.run([os.environ.get('NODE_BIN','node'),'tests/app/conversion-browser.cjs'],cwd=ROOT,env=env,encoding='utf-8',capture_output=True)
 print(browser.stdout);print(browser.stderr if browser.returncode else '')
 results['browser_desktop_mobile']=browser.returncode==0
finally:
 if revoked:sql("GRANT INSERT (id_trace,horodatage,type_action,id_utilisateur,details) ON innovevents_demo.journal_a_emettre TO 'ecf_conversion'@'%';")
 if mongo_stopped:run(['start','mongodb'])
 sql(f"DELETE FROM prospects WHERE email='{email}'; DELETE FROM clients WHERE email='{email}';")
 (ROOT/'tmp/conversion-tests.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print(json.dumps(results,indent=2));raise SystemExit(0 if len(results)==25 and all(results.values()) else 1)
