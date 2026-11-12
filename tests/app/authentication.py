"""Tests HTTP avec cookies ; comptes fictifs propres à cet essai, nettoyés en finally."""
from pathlib import Path
import http.cookiejar, json, os, secrets, subprocess, urllib.request, urllib.error
ROOT=Path(__file__).resolve().parents[2]
DOCKER=os.environ.get('DOCKER_BIN','docker')
PREFIX='auth-'+secrets.token_hex(6)
PASSWORD=(ROOT/'.secrets/demo-password.txt').read_text().strip()
BASE='http://127.0.0.1:5173'
def sql(query):
    p=subprocess.run([DOCKER,'compose','exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],input=query,encoding='utf-8',capture_output=True,cwd=ROOT,check=True)
    return p.stdout.strip()
class Browser:
    def __init__(self):
        self.jar=http.cookiejar.CookieJar(); self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar));self.csrf=''
    def call(self,path,method='GET',data=None,csrf=True,origin=BASE):
        headers={'Origin':origin}
        if data is not None: headers['Content-Type']='application/json'
        if csrf: headers['X-CSRF-Token']=self.csrf
        req=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,method=method,headers=headers)
        try:
            with self.client.open(req,timeout=30) as r: return r.status,json.loads(r.read()),r.headers
        except urllib.error.HTTPError as e: return e.code,json.loads(e.read()),e.headers
    def session(self):
        s,d,h=self.call('/api/auth/session');self.csrf=d['csrf'];return d,h
    def login(self,email,password=PASSWORD,extra=None):
        self.session();s,d,h=self.call('/api/auth/login','POST',{'email':email,'password':password,**(extra or {})})
        if 'csrf' in d:self.csrf=d['csrf']
        return s,d,h
results={}; ids=[]; clients=[]; events=[]
try:
    for tag in ['a','b']:
        email=f'{PREFIX}-{tag}@example.invalid'
        uid=int(sql("INSERT INTO utilisateurs(email,email_normalise,prenom,nom,nom_utilisateur,mot_de_passe_hash) SELECT '"+email+"','"+email+"','Test','Auth','"+PREFIX+tag+"',mot_de_passe_hash FROM utilisateurs WHERE email_normalise='alice@example.invalid'; SELECT LAST_INSERT_ID();"));ids.append(uid)
        cid=int(sql(f"INSERT INTO clients(utilisateur_id,entreprise,contact_nom,contact_prenom,email,telephone) VALUES({uid},'Test Auth','Test','Test','{email}','0000000000'); SELECT LAST_INSERT_ID();"));clients.append(cid)
        eid=int(sql(f"INSERT INTO evenements(client_id,nom,debut,fin) VALUES({cid},'{PREFIX}-{tag}','2031-01-01 08:00:00','2031-01-01 10:00:00'); SELECT LAST_INSERT_ID();"));events.append(eid)
    anon=Browser(); d,h=anon.session()
    results['anonymous_no_user']=d['user'] is None
    cookie=h.get('Set-Cookie','').lower()
    results['cookie_protection']='httponly' in cookie and 'samesite=lax' in cookie and 'path=/api' in cookie
    results['anonymous_private_denied']=anon.call('/api/me/events')[0]==401
    results['csrf_required']=anon.call('/api/auth/login','POST',{'email':'chloe@example.invalid','password':PASSWORD},csrf=False)[0]==403
    results['cross_origin_denied']=anon.call('/api/auth/login','POST',{'email':'chloe@example.invalid','password':PASSWORD},origin='https://foreign.invalid')[0]==403
    email=f'{PREFIX}-a@example.invalid'
    bad=anon.login(email,'incorrect')[:2]; unknown=anon.login(f'{PREFIX}-unknown@example.invalid','incorrect')[:2]
    results['generic_error']=bad==unknown==(401,{'error':'invalid_credentials'})
    b=Browser();b.session(); before=next(iter(b.jar)).value
    s,d,_=b.login(email,extra={'role':'administrateur','id':'1'})
    results['login_and_server_role']=s==200 and d['user']['role']=='client' and d['user']['id']==str(ids[0])
    results['session_rotation']=next(iter(b.jar)).value!=before
    results['no_hash_in_response']='mot_de_passe_hash' not in json.dumps(d) and 'password' not in {k for k in d['user'] if k!='password_change_required'}
    own=b.call('/api/me/events')[1]['events']
    results['ownership']=len(own)==1 and int(own[0]['id'])==events[0]
    results['client_admin_denied']=b.call('/api/admin/logs')[0]==403
    results['client_team_denied']=b.call('/api/team/notes')[0]==403
    sql(f"UPDATE utilisateurs SET statut='suspendu' WHERE id={ids[0]};")
    results['suspension_revokes_session']=b.call('/api/me/events')[0]==401
    results['suspended_login_denied']=b.login(email)[0]==401
    sql(f"UPDATE utilisateurs SET statut='actif',changement_mdp_requis=1 WHERE id={ids[0]};")
    s,d,_=b.login(email)
    results['mandatory_change_blocks_access']=s==200 and d['user']['password_change_required'] and b.call('/api/me/events')[1]=={'error':'password_change_required'}
    sql(f"UPDATE utilisateurs SET changement_mdp_requis=0 WHERE id={ids[0]};")
    b.login(email);b.call('/api/auth/logout','POST')
    results['logout_revokes_session']=b.call('/api/me/events')[0]==401
    employee=Browser();results['employee_login']=employee.login('jose@example.invalid')[0]==200
    results['employee_team_allowed']=employee.call('/api/team/notes')[0]==200
    results['employee_admin_denied']=employee.call('/api/admin/logs')[0]==403
    admin=Browser();results['admin_login']=admin.login('chloe@example.invalid')[0]==200
    results['admin_inherits_team']=admin.call('/api/team/notes')[0]==200
    status,logs,_=admin.call('/api/admin/logs');results['admin_logs_allowed']=status==200
    entries=logs['logs'];results['login_audit_success']=any(x['type']=='CONNEXION_REUSSIE' and x['actor']==str(ids[0]) for x in entries)
    results['login_audit_failure']=any(x['type']=='CONNEXION_ECHOUEE' and x['details'].get('email_utilise')==email for x in entries)
    results['audit_no_credentials']=all('password' not in x['details'] and 'csrf' not in x['details'] for x in entries)
    # Nouvelles sessions : la limite ne dépend pas uniquement du cookie.
    statuses=[Browser().login(f'{PREFIX}-rate@example.invalid','incorrect')[0] for _ in range(11)]
    results['rate_limit_across_sessions']=statuses[:10]==[401]*10 and statuses[10]==429
finally:
    if events:sql('DELETE FROM evenements WHERE id IN ('+','.join(map(str,events))+');')
    if clients:sql('DELETE FROM clients WHERE id IN ('+','.join(map(str,clients))+');')
    if ids:sql('DELETE FROM utilisateurs WHERE id IN ('+','.join(map(str,ids))+');')
    # Les traces des connexions sont conservées : elles ne sont pas effaçables par l’API.
    out=ROOT/'tmp/auth-tests.json';out.parent.mkdir(exist_ok=True)
    out.write_text(json.dumps(results,indent=2),encoding='utf-8')
print(json.dumps(results,indent=2))
raise SystemExit(0 if results and all(results.values()) else 1)
