"""Données fictives isolées, qualification administrateur et contrôles de droits."""
from pathlib import Path
import http.cookiejar,json,os,secrets,subprocess,time,urllib.request,urllib.error
ROOT=Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:5173';DOCKER=os.environ.get('DOCKER_BIN','docker')
email='prospect-test-'+secrets.token_hex(8)+'@example.invalid';results={};ids=[];revoked=False
def sql(query):
 p=subprocess.run([DOCKER,'compose','exec','-T','mysql','sh','/opt/innovevents/client.sh','--default-character-set=utf8mb4','--batch','--skip-column-names','innovevents_demo'],input=query,encoding='utf-8',capture_output=True,cwd=ROOT,check=True);return p.stdout.strip()
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
try:
 values=','.join(f"('Test qualification {i}','Nom','Prénom','{email}','0600000000','Lyon','Séminaire','2031-07-01',20,'Besoin fictif')" for i in range(30))
 sql('INSERT INTO prospects(entreprise,nom,prenom,email,telephone,lieu,type_evenement,date_souhaitee,participants_estimes,besoin) VALUES '+values+';')
 ids=sql(f"SELECT id FROM prospects WHERE email='{email}' ORDER BY id;").splitlines();target=ids[0]
 anon=Browser();admin=Browser();client=Browser();employee=Browser()
 assert admin.login('chloe@example.invalid')[0]==200
 assert client.login('alice@example.invalid')[0]==200
 assert employee.login('jose@example.invalid')[0]==200
 results['anonymous_list_denied']=anon.call('/api/admin/prospects')[0]==401
 results['client_list_denied']=client.call('/api/admin/prospects')[0]==403
 results['employee_detail_denied']=employee.call('/api/admin/prospect?id='+target)[0]==403
 results['client_failure_denied']=client.call('/api/admin/prospect-failure',{'id':target,'message':'Refus interdit'})[0]==403
 results['employee_failure_denied']=employee.call('/api/admin/prospect-failure',{'id':target,'message':'Refus interdit'})[0]==403
 path='/api/admin/prospects?'+urllib.parse.urlencode({'status':'a_contacter','search':email})
 code,page=admin.call(path)
 results['filtered_list_paginated']=code==200 and len(page['prospects'])==25 and page['next'] is not None
 second=admin.call(path+'&before='+page['next'])[1]
 results['pagination_no_loss_or_duplicate']=len(second['prospects'])==5 and second['next'] is None and len({str(x['id']) for x in page['prospects']+second['prospects']})==30
 results['empty_search']=admin.call('/api/admin/prospects?search=definitely-missing-'+secrets.token_hex(8))[1]['prospects']==[]
 results['invalid_filter']=admin.call('/api/admin/prospects?status=bad')[0]==422
 results['invalid_id']=admin.call('/api/admin/prospect?id=1%20OR%201=1')[0]==422
 detail=admin.call('/api/admin/prospect?id='+target)[1]['prospect']
 results['detail_has_request_fields']=all(k in detail for k in ['entreprise','nom','prenom','email','telephone','lieu','type_evenement','date_souhaitee','participants_estimes','besoin']) and detail['email']==email and detail['prenom']=='Prénom' and detail['type_evenement']=='Séminaire'
 results['missing_detail']=admin.call('/api/admin/prospect?id=999999999999999999')[0]==404
 p={'id':target,'message':'Nous ne pouvons pas assurer ce projet fictif.'}
 results['csrf_required']=admin.call('/api/admin/prospect-failure',p,csrf=False)[0]==403
 results['blank_message_rejected']=admin.call('/api/admin/prospect-failure',{**p,'message':' '})[0]==422
 results['long_message_rejected']=admin.call('/api/admin/prospect-failure',{**p,'message':'x'*2001})[0]==422
 before=int(sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE type='qualification_echec';"))
 results['failure_recorded']=admin.call('/api/admin/prospect-failure',p)==(200,{'status':'echoue'})
 results['state_and_message_saved']=sql(f"SELECT statut,qualifie_le IS NOT NULL,message_echec IS NOT NULL FROM prospects WHERE id={target};")=='echoue\t1\t1'
 results['retry_same_message']=admin.call('/api/admin/prospect-failure',p)==(200,{'status':'echoue'})
 results['one_mail_for_repeated_action']=int(sql("SELECT COUNT(*) FROM courriels_a_envoyer WHERE type='qualification_echec';"))==before+1
 results['different_message_conflict']=admin.call('/api/admin/prospect-failure',{**p,'message':'Autre décision'})[0]==409
 results['failed_filter']=any(str(x['id'])==target for x in admin.call('/api/admin/prospects?'+urllib.parse.urlencode({'status':'echoue','search':email}))[1]['prospects'])
 # Injecter une panne de mise en file : la qualification doit être annulée.
 revoked=True;sql("REVOKE INSERT ON innovevents_demo.courriels_a_envoyer FROM 'ecf_prospects_admin'@'%';")
 results['queue_failure_503']=admin.call('/api/admin/prospect-failure',{'id':ids[1],'message':'Test annulation transaction'})[0]==503
 results['queue_failure_rollback']=sql(f"SELECT statut,message_echec IS NULL,qualifie_le IS NULL FROM prospects WHERE id={ids[1]};")=='a_contacter\t1\t1'
 sql("GRANT INSERT ON innovevents_demo.courriels_a_envoyer TO 'ecf_prospects_admin'@'%';");revoked=False
 sql(f"UPDATE prospects SET statut='converti',client_id=(SELECT MIN(id) FROM clients),qualifie_le=UTC_TIMESTAMP() WHERE id={ids[2]};")
 results['converted_cannot_fail']=admin.call('/api/admin/prospect-failure',{'id':ids[2],'message':'Action impossible'})[0]==409
 received=False
 for _ in range(15):
  with urllib.request.urlopen('http://127.0.0.1:8025/api/v1/messages?limit=100') as r:messages=json.loads(r.read())['messages']
  for m in messages:
   if m['Subject']=='Suite à votre demande Innov’Events' and any(t['Address']==email for t in m['To']):
    with urllib.request.urlopen('http://127.0.0.1:8025/api/v1/message/'+m['ID']) as r:received=json.loads(r.read())['Text'].strip()==p['message']
  if received:break
  time.sleep(1)
 results['prospect_mail_received']=received
 env={**os.environ,'PROSPECT_TEST_IDS':','.join(ids[-2:]),'PROSPECT_TEST_EMAIL':email}
 browser=subprocess.run([os.environ.get('NODE_BIN','node'),'tests/app/prospects-browser.cjs'],cwd=ROOT,env=env,encoding='utf-8',capture_output=True)
 print(browser.stdout);print(browser.stderr if browser.returncode else '')
 results['browser_desktop_mobile']=browser.returncode==0
finally:
 if revoked:sql("GRANT INSERT ON innovevents_demo.courriels_a_envoyer TO 'ecf_prospects_admin'@'%';")
 sql(f"DELETE FROM prospects WHERE email='{email}';")
 (ROOT/'tmp/prospects-tests.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print(json.dumps(results,indent=2));raise SystemExit(0 if len(results)==26 and all(results.values()) else 1)
