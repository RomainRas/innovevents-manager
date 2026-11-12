"""Contrôles HTTP des demandes publiques ; nettoyage par clés propres à cet essai."""
from pathlib import Path
import concurrent.futures, http.cookiejar, json, os, secrets, subprocess, time, urllib.request, urllib.error
ROOT=Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:5173'
DOCKER=os.environ.get('DOCKER_BIN','docker');keys=[];results={}
email='quote-test-'+secrets.token_hex(8)+'@example.invalid'
def sql(query):
    p=subprocess.run([DOCKER,'compose','exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],input=query,encoding='utf-8',capture_output=True,cwd=ROOT,check=True)
    return p.stdout.strip()
class Browser:
    def __init__(self):
        self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
        self.csrf=self.call('/api/auth/session')[1]['csrf']
    def call(self,path,data=None,csrf=True):
        req=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers={'Origin':BASE,'Content-Type':'application/json','X-CSRF-Token':self.csrf if csrf else ''})
        try:
            with self.client.open(req,timeout=30) as r:s,d=r.status,json.loads(r.read())
        except urllib.error.HTTPError as e:s,d=e.code,json.loads(e.read())
        if 'csrf' in d:self.csrf=d['csrf']
        return s,d
    def login(self,email):return self.call('/api/auth/login',{'email':email,'password':(ROOT/'.secrets/demo-password.txt').read_text().strip()})
def payload():
    key=secrets.token_hex(32);keys.append(key)
    return dict(submission_key=key,entreprise='Entreprise test',nom='Test',prenom='Demande',email=email,telephone='+33 6 00 00 00 00',lieu='Lyon',type_evenement='Séminaire libre',date_souhaitee='2031-06-10',participants_estimes='42',besoin='Projet fictif de validation.')
def count():return int(sql(f"SELECT COUNT(*) FROM prospects WHERE email='{email}';"))
original=None;admin=None
try:
    b=Browser();p=payload();original=b.call('/api/quote-request/config')[1]['message']
    results['csrf_required']=b.call('/api/quote-requests',p,csrf=False)[0]==403
    missing=[]
    for field in p:
        if field=='submission_key':continue
        code,data=b.call('/api/quote-requests',{**p,field:''});missing.append(code==422 and field in data.get('fields',{}))
    results['ten_required_fields']=len(missing)==10 and all(missing)
    results['validation_does_not_insert']=count()==0
    results['invalid_email']=b.call('/api/quote-requests',{**p,'email':'bad@'})[0]==422
    results['invalid_date']=b.call('/api/quote-requests',{**p,'date_souhaitee':'2031-02-30'})[0]==422
    results['invalid_count']=all(b.call('/api/quote-requests',{**p,'participants_estimes':v})[0]==422 for v in [0,-1,1.5,'1e3','4294967296'])
    results['field_length']=b.call('/api/quote-requests',{**p,'entreprise':'a'*201})[0]==422
    code,data=b.call('/api/quote-requests',{**p,'statut':'converti','client_id':1})
    results['anonymous_submission']=code==200 and data.get('message')==original
    results['status_server_controlled']=sql(f"SELECT statut,client_id IS NULL FROM prospects WHERE email='{email}';")=='a_contacter\t1'
    # Rejouer après avoir ignoré la première réponse simule une réponse perdue côté client.
    results['retry_same_response']=b.call('/api/quote-requests',p)==(code,data) and count()==1
    results['changed_payload_conflict']=b.call('/api/quote-requests',{**p,'besoin':'Autre besoin'})[0]==409 and count()==1
    p2=payload()
    def send(_):return Browser().call('/api/quote-requests',p2)[0]
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:statuses=list(pool.map(send,range(2)))
    results['parallel_http_no_duplicate']=statuses==[200,200] and count()==2
    client=Browser();client.login('alice@example.invalid')
    results['connected_submission']=client.call('/api/quote-requests',payload())[0]==200
    results['anonymous_settings_denied']=b.call('/api/admin/quote-thanks',{'message':'interdit'})[0]==401
    results['client_settings_denied']=client.call('/api/admin/quote-thanks',{'message':'interdit'})[0]==403
    admin=Browser();assert admin.login('chloe@example.invalid')[0]==200
    new='Merci pour ce projet fictif. <b>Texte, sans HTML.</b>'
    results['admin_changes_thanks']=admin.call('/api/admin/quote-thanks',{'message':new})==(200,{'message':new})
    results['blank_thanks_denied']=admin.call('/api/admin/quote-thanks',{'message':' '})[0]==422
    results['config_updated']=b.call('/api/quote-request/config')[1]['message']==new
    results['retry_keeps_original_confirmation']=b.call('/api/quote-requests',p)[1]['message']==original
    results['new_request_uses_new_thanks']=b.call('/api/quote-requests',payload())[1]['message']==new
    # Examiner uniquement les messages fictifs de ce test ; ne pas afficher leur contenu.
    found=False
    for _ in range(15):
        with urllib.request.urlopen('http://127.0.0.1:8025/api/v1/messages?limit=100') as r:messages=json.loads(r.read())['messages']
        for m in messages:
            if m['Subject']!='Nouvelle demande de devis Innov’Events':continue
            with urllib.request.urlopen('http://127.0.0.1:8025/api/v1/message/'+m['ID']) as r:body=json.loads(r.read())['Text']
            if email in body and any(t['Address']=='agence@example.invalid' for t in m['To']):found=True;break
        if found:break
        time.sleep(1)
    results['agency_mail_received']=found
finally:
    if original is not None and admin is not None:
        # Rétablir exactement le paramètre via le compte administrateur.
        if admin.call('/api/admin/quote-thanks',{'message':original})[0]!=200:raise RuntimeError('Rétablissement du remerciement à vérifier')
    if keys:sql('DELETE FROM soumissions_devis WHERE cle IN ('+','.join("'"+k+"'" for k in keys)+');')
    sql(f"DELETE FROM prospects WHERE email='{email}';")
    (ROOT/'tmp/quote-tests.json').write_text(json.dumps(results,indent=2),encoding='utf-8')
print(json.dumps(results,indent=2));raise SystemExit(0 if len(results)==21 and all(results.values()) else 1)
