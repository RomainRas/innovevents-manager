"""Transmission du devis : envoi plateforme ou manuel, compte client, espace client et droits."""
from pathlib import Path
import hashlib,http.cookiejar,io,json,os,re,secrets,subprocess,time,urllib.error,urllib.parse,urllib.request,uuid
from PIL import Image
ROOT=Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:5173';MAILPIT='http://127.0.0.1:8025'
COMPOSE=[os.environ.get('DOCKER_BIN','docker'),'compose','-f','compose.yaml','-f','compose.app.yaml'];results={}
tag=secrets.token_hex(8);email=f'envoi-test-{tag}@example.invalid'
def sql(q):
 r=subprocess.run(COMPOSE+['exec','-T','mysql','sh','/opt/innovevents/client.sh','--default-character-set=utf8mb4','--batch','--skip-column-names','innovevents_demo'],input=q,encoding='utf-8',capture_output=True,cwd=ROOT)
 if r.returncode:raise RuntimeError('SQL de test refusé')
 return r.stdout.strip()
def compose(*args):subprocess.run(COMPOSE+list(args),cwd=ROOT,capture_output=True,check=True)
class Browser:
 def __init__(self):
  self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf='';self.call('/api/auth/session')
 def call(self,path,data=None,mime='application/json',csrf=True):
  raw=json.dumps(data).encode() if data is not None and not isinstance(data,bytes) else data
  req=urllib.request.Request(BASE+path,data=raw,headers={'Origin':BASE,'Content-Type':mime,'X-CSRF-Token':self.csrf if csrf else ''})
  try:r=self.client.open(req,timeout=30)
  except urllib.error.HTTPError as e:r=e
  with r:body=r.read();headers=r.headers;s=r.code
  d=json.loads(body) if headers.get('Content-Type','').startswith('application/json') else body
  if isinstance(d,dict) and 'csrf' in d:self.csrf=d['csrf']
  return s,d,headers
 def login(self,address,password=None):return self.call('/api/auth/login',{'email':address,'password':password or (ROOT/'.secrets/demo-password.txt').read_text().strip()})
 def upload(self,eid,image):
  boundary='test-'+secrets.token_hex(16);parts=[]
  for k,v in {'id':eid,'revision':1,'alternative':'Salle de réception'}.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
  parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="salle.png"\r\nContent-Type: image/png\r\n\r\n'.encode()+image+b'\r\n');parts.append(f'--{boundary}--\r\n'.encode())
  return self.call('/api/admin/event-image-upload',b''.join(parts),'multipart/form-data; boundary='+boundary)
def mailpit(path):
 with urllib.request.urlopen(MAILPIT+path,timeout=10) as r:return r.read()
def messages(address):return json.loads(mailpit('/api/v1/search?'+urllib.parse.urlencode({'query':f'to:"{address}"'})))['messages']
def picture():
 b=io.BytesIO();Image.new('RGB',(120,80),(12,127,140)).save(b,format='PNG');return b.getvalue()
def key():return str(uuid.uuid4())
worker_stopped=False
try:
 sql(f"INSERT INTO clients(entreprise,contact_nom,contact_prenom,email,telephone) VALUES('Maison Lumière & Fils','Durand','Léa','{email}','0600000000'),('Société homonyme','Martin','Paul','alice@example.invalid','0600000001');")
 cid,cid2=sql(f"SELECT id FROM clients WHERE email IN ('{email}','alice@example.invalid') AND utilisateur_id IS NULL ORDER BY id;").splitlines()
 sql(f"INSERT INTO evenements(client_id,nom,debut,fin,lieu) VALUES({cid},'Gala de printemps','2031-04-10 17:00:00','2031-04-10 22:00:00','Lyon'),({cid},'Brouillon non transmis','2031-05-10 08:00:00','2031-05-10 16:00:00','Lyon'),({cid2},'Conflit de compte','2031-06-10 08:00:00','2031-06-10 16:00:00','Lyon'),({cid},'Envoi ordinateur','2031-07-10 08:00:00','2031-07-10 16:00:00','Lyon'),({cid},'Envoi téléphone','2031-08-10 08:00:00','2031-08-10 16:00:00','Lyon');")
 ids=sql(f"SELECT id FROM evenements WHERE client_id IN ({cid},{cid2}) ORDER BY id;").splitlines();eid,draft_eid,conflict_eid=ids[0],ids[1],ids[2]
 admin=Browser();anon=Browser();alice=Browser();employee=Browser()
 for b,address in [(admin,'chloe@example.invalid'),(alice,'alice@example.invalid'),(employee,'jose@example.invalid')]:assert b.login(address)[0]==200
 lines=[{'libelle':'Location de la salle','montant_ht':'1000.00'},{'libelle':'Cocktail dînatoire','montant_ht':'500.00'}]
 docs={}
 for i in ids:
  assert admin.call('/api/admin/quote-draft-save',{'event_id':i,'revision':0,'taux_tva':'20','prestations':lines})[0]==200
  assert admin.upload(i,picture())[0]==200
  code,d,_=admin.call('/api/admin/quote-pdf-generate',{'event_id':i,'revision':1});assert code==200,d;docs[i]=d['document_id']
 send='/api/admin/quote-send';status='/api/admin/quote-transmission?event_id='+eid
 payload={'event_id':eid,'document_id':docs[eid],'mode':'automatique','cle':key()}
 results['anonymous_denied']=anon.call(send,payload)[0]==401 and anon.call(status)[0]==401
 results['client_denied']=alice.call(send,payload)[0]==403 and alice.call(status)[0]==403
 results['employee_denied']=employee.call(send,payload)[0]==403 and employee.call(status)[0]==403
 results['csrf_required']=admin.call(send,payload,csrf=False)[0]==403
 results['invalid_input_refused']=all(admin.call(send,{**payload,**x})[0]==422 for x in [{'mode':'courrier'},{'cle':'abc'},{'mode':'manuel'},{'document_id':'x'}])
 results['other_document_refused']=admin.call(send,{**payload,'document_id':docs[draft_eid]})[0]==409
 s=admin.call(status)[1]
 results['status_before']=s['statut']=='brouillon' and s['document_id']==docs[eid] and s['compte_client'] is False and s['destinataire']==email and s['transmissions']==[]
 # Traitement des courriels arrêté : la transmission reste en attente, de façon déterministe.
 compose('stop','mail_worker');worker_stopped=True
 code,sent,_=admin.call(send,payload);assert code==200,sent
 results['automatic_send_recorded']=sent['statut']=='etude_client' and sent['compte_cree'] is True and sent['transmission']['etat']=='en_attente' and sent['transmission']['mode']=='automatique'
 code,again,_=admin.call(send,payload)
 results['lost_response_replay']=code==200 and again['repetition'] is True and again['transmission']['id']==sent['transmission']['id']
 results['replay_no_duplicate']=sql(f"SELECT COUNT(*) FROM transmissions_devis WHERE destinataire='{email}';")=='1' and sql(f"SELECT COUNT(*) FROM utilisateurs WHERE email_normalise='{email}';")=='1' and sql(f"SELECT COUNT(*) FROM courriels_a_envoyer WHERE type='devis' AND etat='attente' AND id=(SELECT courriel_id FROM transmissions_devis WHERE id={sent['transmission']['id']});")=='1'
 results['key_reuse_conflict']=admin.call(send,{**payload,'mode':'manuel','confirmation':True})[0]==409
 results['pending_blocks_new_send']=admin.call(send,{**payload,'cle':key()})[0]==409
 results['version_frozen']=sql(f"SELECT CONCAT(v.statut,'|',v.figee_le IS NOT NULL,'|',v.transmise_le IS NOT NULL) FROM versions_devis v JOIN devis d ON d.version_courante_id=v.id WHERE d.evenement_id={eid};")=='etude_client|1|1'
 results['frozen_quote_not_editable']=admin.call('/api/admin/quote-draft-save',{'event_id':eid,'revision':1,'taux_tva':'10','prestations':lines})[0]==409 and admin.call('/api/admin/quote-pdf-generate',{'event_id':eid,'revision':1})[0]==409 and admin.call('/api/admin/quote-draft?event_id='+eid)[1]['quote']['editable'] is False
 compose('start','mail_worker');worker_stopped=False
 state=''
 for _ in range(30):
  state=sql(f"SELECT etat FROM transmissions_devis WHERE id={sent['transmission']['id']};")
  if state=='confirme':break
  time.sleep(1)
 results['worker_confirms_transmission']=state=='confirme' and admin.call(status)[1]['transmissions'][0]['etat']=='confirme'
 pdf=admin_pdf=None;found=messages(email);quote_mail=[m for m in found if m['Subject'].startswith('Votre devis')];account_mail=[m for m in found if m['Subject']=='Votre espace client Innov’Events']
 if len(quote_mail)==1:
  m=json.loads(mailpit('/api/v1/message/'+quote_mail[0]['ID']));att=[a for a in m['Attachments'] if a['ContentType']=='application/pdf']
  if len(att)==1:pdf=mailpit(f"/api/v1/message/{quote_mail[0]['ID']}/part/{att[0]['PartID']}")
 stored=sql(f"SELECT empreinte_sha256 FROM documents_devis WHERE id={docs[eid]};")
 results['email_with_pdf_attachment']=pdf is not None and pdf.startswith(b'%PDF-') and hashlib.sha256(pdf).hexdigest()==stored
 password=None
 if len(account_mail)==1:
  text=json.loads(mailpit('/api/v1/message/'+account_mail[0]['ID']))['Text'];match=re.search(r'Mot de passe par défaut : (\S+)',text);password=match and match.group(1)
 results['account_email_sent']=bool(password) and sql(f"SELECT CONCAT(u.role,'|',u.changement_mdp_requis,'|',c.utilisateur_id=u.id) FROM utilisateurs u JOIN clients c ON c.id={cid} WHERE u.email_normalise='{email}';")=='client|1|1'
 customer=Browser();code,d,_=customer.login(email,password)
 results['first_login_requires_change']=code==200 and d['user']['password_change_required'] is True and customer.call('/api/me/quotes')[0]==403
 new_password='Nouveau!'+secrets.token_hex(6)+'A1'
 code,_,_=customer.call('/api/auth/password',{'current_password':password,'password':new_password,'confirmation':new_password})
 results['password_changed']=code==200 and customer.login(email,new_password)[0]==200
 code,listing,_=customer.call('/api/me/quotes');q=listing.get('devis',[]) if code==200 else []
 results['client_sees_transmitted_only']=code==200 and len(q)==1 and q[0]['statut']=='etude_client' and q[0]['total_ttc']=='1800.00' and q[0]['evenement']['nom']=='Gala de printemps' and q[0]['evenement']['debut']=='2031-04-10T19:00' and len(q[0]['prestations'])==2 and q[0]['document_id']==docs[eid]
 url='/api/me/quote-pdf?id='+docs[eid];code,body,headers=customer.call(url)
 results['client_downloads_pdf']=code==200 and headers['Content-Type']=='application/pdf' and headers['Cache-Control']=='no-store' and hashlib.sha256(body).hexdigest()==stored
 results['other_client_isolated']=alice.call(url)[0]==404 and all(x['document_id']!=docs[eid] for x in alice.call('/api/me/quotes')[1]['devis'])
 results['draft_document_hidden']=customer.call('/api/me/quote-pdf?id='+docs[draft_eid])[0]==404
 results['client_routes_roles']=anon.call('/api/me/quotes')[0]==401 and admin.call('/api/me/quotes')[0]==403 and employee.call(url)[0]==403
 manual={'event_id':eid,'document_id':docs[eid],'mode':'manuel','cle':key()}
 results['manual_requires_confirmation']=admin.call(send,manual)[0]==422
 code,d,_=admin.call(send,{**manual,'confirmation':True})
 results['manual_declaration']=code==200 and d['transmission']['etat']=='confirme' and d['compte_cree'] is False and len(admin.call(status)[1]['transmissions'])==2
 code,d,_=admin.call(send,{'event_id':conflict_eid,'document_id':docs[conflict_eid],'mode':'automatique','cle':key()})
 results['existing_account_not_linked']=code==409 and d.get('error')=='account_email_conflict' and sql(f"SELECT CONCAT(c.utilisateur_id IS NULL,'|',v.statut) FROM clients c JOIN evenements e ON e.client_id=c.id JOIN devis d ON d.evenement_id=e.id JOIN versions_devis v ON v.id=d.version_courante_id WHERE e.id={conflict_eid};")=='1|brouillon' and sql(f"SELECT COUNT(*) FROM transmissions_devis t JOIN documents_devis p ON p.id=t.document_id WHERE p.id={docs[conflict_eid]};")=='0'
 r=subprocess.run(COMPOSE+['exec','-T','api','php','tests/quote-send-permissions.php'],encoding='utf-8',capture_output=True,cwd=ROOT);print(r.stdout)
 results['sql_permissions']=r.returncode==0
 env={**os.environ,'SEND_TEST_IDS':','.join(ids[3:]),'SEND_TEST_EMAIL':email,'SEND_TEST_PASSWORD':new_password}
 b=subprocess.run([os.environ.get('NODE_BIN','node'),'tests/app/quote-transmission-browser.cjs'],cwd=ROOT,env=env,encoding='utf-8',capture_output=True);print(b.stdout);print(b.stderr if b.returncode else '');results['browser']=b.returncode==0
finally:
 if worker_stopped:compose('start','mail_worker')
 if 'cid' in locals():
  clients=f"{cid},{cid2}"
  sql(f"DELETE t FROM transmissions_devis t JOIN documents_devis p ON p.id=t.document_id JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id IN ({clients});")
  for table in ['documents_devis','prestations_devis']:sql(f"DELETE p FROM {table} p JOIN versions_devis v ON v.id=p.version_id JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id IN ({clients});")
  sql(f"UPDATE devis d JOIN evenements e ON e.id=d.evenement_id SET d.version_courante_id=NULL WHERE e.client_id IN ({clients}); DELETE v FROM versions_devis v JOIN devis d ON d.id=v.devis_id JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id IN ({clients}); DELETE d FROM devis d JOIN evenements e ON e.id=d.evenement_id WHERE e.client_id IN ({clients}); DELETE FROM evenements WHERE client_id IN ({clients}); UPDATE clients SET utilisateur_id=NULL WHERE id IN ({clients}); DELETE FROM clients WHERE id IN ({clients}); DELETE FROM utilisateurs WHERE email_normalise='{email}';")
 (ROOT/'tmp').mkdir(exist_ok=True);(ROOT/'tmp/quote-transmission-tests.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print(json.dumps(results,indent=2));raise SystemExit(0 if len(results)==29 and all(results.values()) else 1)
