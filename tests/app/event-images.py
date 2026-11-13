"""Images privées : format réel, réencodage, droits, reprise et navigateur."""
from pathlib import Path
import http.cookiejar,json,os,secrets,subprocess,urllib.request,urllib.error,io
from PIL import Image
ROOT=Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:5173';COMPOSE=[os.environ.get('DOCKER_BIN','docker'),'compose','-f','compose.yaml','-f','compose.app.yaml'];results={}
email='image-test-'+secrets.token_hex(8)+'@example.invalid'
def sql(q):
 r=subprocess.run(COMPOSE+['exec','-T','mysql','sh','/opt/innovevents/client.sh','--default-character-set=utf8mb4','--batch','--skip-column-names','innovevents_demo'],input=q,encoding='utf-8',capture_output=True,cwd=ROOT)
 if r.returncode:raise RuntimeError('SQL de test refusé')
 return r.stdout.strip()
class Browser:
 def __init__(self):
  self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf='';self.call('/api/auth/session')
 def call(self,path,data=None,mime='application/json',csrf=True):
  raw=json.dumps(data).encode() if data is not None and not isinstance(data,bytes) else data
  req=urllib.request.Request(BASE+path,data=raw,headers={'Origin':BASE,'Content-Type':mime,'X-CSRF-Token':self.csrf if csrf else ''})
  try:r=self.client.open(req,timeout=30)
  except urllib.error.HTTPError as e:r=e
  with r:
   body=r.read();headers=r.headers;s=r.code
  d=json.loads(body) if headers.get('Content-Type','').startswith('application/json') else body
  if isinstance(d,dict) and 'csrf' in d:self.csrf=d['csrf']
  return s,d,headers
 def login(self,address):return self.call('/api/auth/login',{'email':address,'password':(ROOT/'.secrets/demo-password.txt').read_text().strip()})
 def upload(self,eid,revision,image,alt='Salle de séminaire',mime='image/png',csrf=True):
  boundary='test-'+secrets.token_hex(16);parts=[]
  for k,v in {'id':eid,'revision':revision,'alternative':alt}.items():parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
  parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="../../lieu.php"\r\nContent-Type: {mime}\r\n\r\n'.encode()+image+b'\r\n');parts.append(f'--{boundary}--\r\n'.encode())
  return self.call('/api/admin/event-image-upload',b''.join(parts),'multipart/form-data; boundary='+boundary,csrf)
def picture(fmt='PNG',size=(120,80)):
 b=io.BytesIO();Image.new('RGB',size,(12,127,140)).save(b,format=fmt);return b.getvalue()
try:
 sql(f"INSERT INTO clients(entreprise,contact_nom,contact_prenom,email,telephone) VALUES('Test image','Nom','Prénom','{email}','0600000000');")
 cid=sql(f"SELECT id FROM clients WHERE email='{email}';")
 sql(f"INSERT INTO evenements(client_id,nom) VALUES({cid},'Image test'),({cid},'Image ordinateur'),({cid},'Image téléphone');")
 ids=sql(f"SELECT id FROM evenements WHERE client_id={cid} ORDER BY id;").splitlines();eid=ids[0]
 admin=Browser();anon=Browser();client=Browser();employee=Browser()
 for b,address in [(admin,'chloe@example.invalid'),(client,'alice@example.invalid'),(employee,'jose@example.invalid')]:assert b.login(address)[0]==200
 png=picture();jpg=picture('JPEG');endpoint='/api/admin/event-image?id='+eid
 results['anonymous_upload_denied']=anon.upload(eid,1,png)[0]==401
 results['client_upload_denied']=client.upload(eid,1,png)[0]==403
 results['employee_upload_denied']=employee.upload(eid,1,png)[0]==403
 results['csrf_required']=admin.upload(eid,1,png,csrf=False)[0]==403
 results['missing_image_404']=admin.call(endpoint)[0]==404
 results['alternative_required']=admin.upload(eid,1,png,alt='')[0]==422
 results['fake_png_refused']=admin.upload(eid,1,b'<?php echo "bad"; ?>')[0]==422
 results['svg_refused']=admin.upload(eid,1,b'<svg xmlns="http://www.w3.org/2000/svg"></svg>',mime='image/svg+xml')[0]==422
 results['truncated_png_refused']=admin.upload(eid,1,png[:40])[0]==422
 results['oversized_dimensions_refused']=admin.upload(eid,1,picture(size=(4097,1)))[0]==422
 results['oversized_file_refused']=admin.upload(eid,1,png+b'X'*(5*1024*1024))[0]==413
 code,data,_=admin.upload(eid,1,png+b'<?php appended-marker ?>');results['png_saved']=code==200 and data['revision']==2
 code,body,headers=admin.call(endpoint)
 results['reencoded_and_private_headers']=code==200 and b'appended-marker' not in body and headers['Content-Type']=='image/png' and headers['X-Content-Type-Options']=='nosniff' and headers['Cache-Control']=='no-store'
 results['valid_image_output']=Image.open(io.BytesIO(body)).size==(120,80)
 results['untrusted_filename_ignored']=sql(f"SELECT image_reference REGEXP '^[a-f0-9]{{64}}[.]png$' FROM evenements WHERE id={eid};")=='1'
 results['anonymous_read_denied']=anon.call(endpoint)[0]==401
 results['client_read_denied']=client.call(endpoint)[0]==403
 results['employee_read_denied']=employee.call(endpoint)[0]==403
 results['identical_retry']=admin.upload(eid,1,png)[1]==data
 results['stale_changed_image_refused']=admin.upload(eid,1,jpg)[0]==409
 results['jpeg_replacement']=admin.upload(eid,2,jpg,alt='Vue de la salle',mime='image/jpeg')[0]==200 and admin.call(endpoint)[2]['Content-Type']=='image/jpeg'
 details=admin.call('/api/admin/event?id='+eid)[1]['event'];results['detail_reflects_image']=details['has_image'] and details['image_alternative']=='Vue de la salle' and details['revision']==3
 results['publication_unchanged']=sql(f"SELECT statut,visible_demande,accord_publication FROM evenements WHERE id={eid};")=='brouillon\t0\t0'
 sql(f"UPDATE evenements SET statut='accepte' WHERE id={eid};")
 results['non_draft_upload_refused']=admin.upload(eid,3,png)[0]==409
 (ROOT/'tmp/event-image-fixture.png').write_bytes(png);(ROOT/'tmp/event-image-fixture.jpg').write_bytes(jpg)
 env={**os.environ,'EVENT_IMAGE_IDS':','.join(ids[1:])}
 b=subprocess.run([os.environ.get('NODE_BIN','node'),'tests/app/event-images-browser.cjs'],cwd=ROOT,env=env,encoding='utf-8',capture_output=True)
 print(b.stdout);print(b.stderr if b.returncode else '');results['browser']=b.returncode==0
finally:
 sql(f"DELETE FROM evenements WHERE client_id IN(SELECT id FROM clients WHERE email='{email}'); DELETE FROM clients WHERE email='{email}';")
 (ROOT/'tmp/event-images-tests.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print(json.dumps(results,indent=2));raise SystemExit(0 if len(results)==25 and all(results.values()) else 1)
