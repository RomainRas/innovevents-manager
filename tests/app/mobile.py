"""Contrôles HTTP de l'API de l'application mobile : accès réservé à l'équipe avec accès mobile, tableau de bord,
fiche événement, fiche contact actionnable et note rapide. L'accès mobile retiré et la note créée sont restaurés."""
from pathlib import Path
import http.cookiejar, json, os, subprocess, urllib.request, urllib.error
ROOT=Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:5173'
DOCKER=os.environ.get('DOCKER_BIN','docker');results={}
COMPOSE=[DOCKER,'compose','-f','compose.yaml','-f','compose.app.yaml']
def sql(query):
    p=subprocess.run(COMPOSE+['exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],input='SET NAMES utf8mb4;'+query,encoding='utf-8',capture_output=True,cwd=ROOT,check=True)
    return p.stdout.strip()
class Browser:
    def __init__(self):
        self.client=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()));self.csrf=''
        self.csrf=self.call('/api/auth/session')[1]['csrf']
    def raw(self,path):
        try:
            with self.client.open(urllib.request.Request(BASE+path),timeout=30) as r:return r.status,dict(r.headers),r.read()
        except urllib.error.HTTPError as e:return e.code,dict(e.headers),e.read()
    def call(self,path,data=None,csrf=True):
        req=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers={'Origin':BASE,'Content-Type':'application/json','X-CSRF-Token':self.csrf if csrf else ''})
        try:
            with self.client.open(req,timeout=30) as r:s,d=r.status,json.loads(r.read())
        except urllib.error.HTTPError as e:s,d=e.code,json.loads(e.read())
        if 'csrf' in d:self.csrf=d['csrf']
        return s,d
    def login(self,address):return self.call('/api/auth/login',{'email':address,'password':(ROOT/'.secrets/demo-password.txt').read_text().strip()})
subprocess.run(COMPOSE+['exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json'],cwd=ROOT,check=True)
jose_id=sql("SELECT id FROM utilisateurs WHERE email_normalise='jose@example.invalid';")
event=sql("SELECT id FROM evenements WHERE nom='Lancement produit Horizon';")
note_text='Note rapide mobile : le client confirme 95 invités.'
try:
    anonymous=Browser();alice=Browser();jose=Browser();chloe=Browser()
    assert alice.login('alice@example.invalid')[0]==200 and jose.login('jose@example.invalid')[0]==200 and chloe.login('chloe@example.invalid')[0]==200
    results['login_required']=anonymous.call('/api/mobile/events')[0]==401
    results['clients_refused']=alice.call('/api/mobile/events')[0]==403 and alice.call('/api/mobile/note',{'evenement_id':event,'contenu':'x'})[0]==403
    results['csrf_required']=jose.call('/api/mobile/note',{'evenement_id':event,'contenu':'Sans jeton'},csrf=False)[0]==403

    # Tableau de bord : tous les événements à venir, du plus proche au plus lointain, sans limite à trois.
    code,data=jose.call('/api/mobile/events');events=data.get('evenements',[])
    expected=sql("SELECT COUNT(*) FROM evenements WHERE statut<>'annule' AND debut IS NOT NULL AND (fin>=UTC_TIMESTAMP() OR (fin IS NULL AND debut>=UTC_TIMESTAMP()));")
    results['dashboard_all_upcoming']=code==200 and len(events)==int(expected) and len(events)>3
    results['dashboard_sorted']=[e['debut'] for e in events]==sorted(e['debut'] for e in events)
    results['dashboard_no_past_or_cancelled']=all(e['statut']!='annule' for e in events) and 'Convention annuelle Horizon' not in [e['nom'] for e in events]
    results['dashboard_fields']=all(set(e)=={'id','nom','debut','fin','lieu','statut','client'} for e in events)
    results['admin_mobile_access']=chloe.call('/api/mobile/events')[0]==200

    # Fiche événement concise et fiche contact actionnable.
    code,data=jose.call('/api/mobile/event?id='+event);ev=data.get('evenement',{})
    results['event_key_info']=code==200 and ev['nom']=='Lancement produit Horizon' and ev['lieu'] and ev['debut'] and ev['fin'] and ev['client']['entreprise']
    results['event_unknown']=jose.call('/api/mobile/event?id=999999999')[0]==404 and jose.call('/api/mobile/event?id=abc')[0]==422
    code,data=jose.call('/api/mobile/client?id='+ev['client']['id']);c=data.get('client',{})
    results['contact_actions']=code==200 and c['telephone_composable'] and all(ch.isdigit() or ch=='+' for ch in c['telephone_composable']) and '@' in c['email'] and 'Lyon' in (c['adresse'] or '')
    results['contact_no_private_fields']=set(c)=={'id','entreprise','contact','telephone','telephone_composable','email','adresse'}
    results['contact_unknown']=jose.call('/api/mobile/client?id=999999999')[0]==404

    # Note rapide : enregistrée, visible sur la fiche, dans l'espace employé et le tableau de bord.
    results['note_empty_refused']=jose.call('/api/mobile/note',{'evenement_id':event,'contenu':'  '})[0]==422
    code,data=jose.call('/api/mobile/note',{'evenement_id':event,'contenu':note_text})
    results['note_saved']=code==200 and data['notes'][0]['contenu']==note_text and data['notes'][0]['auteur']=='José'
    results['note_shared_with_team']=any(n['contenu']==note_text for n in chloe.call('/api/team/event?id='+event)[1]['notes'])
    results['note_unknown_event']=jose.call('/api/mobile/note',{'evenement_id':'999999999','contenu':'Perdue'})[0]==404

    # Accès mobile retiré : refus immédiat, y compris pour la session ouverte.
    sql(f"UPDATE utilisateurs SET acces_mobile=0 WHERE id={jose_id};")
    code,data=jose.call('/api/mobile/events')
    results['mobile_access_required']=code==403 and data.get('error')=='mobile_access_required'
    results['team_space_still_available']=jose.call('/api/team/events')[0]==200
    sql(f"UPDATE utilisateurs SET acces_mobile=1 WHERE id={jose_id};")

    # Application installable : manifeste, service worker et icônes servis.
    code,headers,body=anonymous.raw('/mobile/manifest.webmanifest');manifest=json.loads(body)
    results['manifest']=code==200 and manifest['display']=='standalone' and manifest['scope']=='/mobile/' and {'192x192','512x512'}<={i['sizes'] for i in manifest['icons']} and any(i['purpose']=='maskable' for i in manifest['icons'])
    results['icons_served']=all(anonymous.raw('/mobile/'+i)[0]==200 for i in ('icon-192.png','icon-512.png','icon-maskable-512.png'))
    code,headers,body=anonymous.raw('/mobile/sw.js');results['service_worker']=code==200 and b"clear-data" in body and b"POST" in body
finally:
    sql(f"UPDATE utilisateurs SET acces_mobile=1 WHERE id={jose_id};")
    sql(f"DELETE FROM notes_evenement WHERE contenu='{note_text}';")
    subprocess.run(COMPOSE+['exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json'],cwd=ROOT)
for k,v in results.items():print(('OK  ' if v else 'ÉCHEC ')+k)
print(f"{sum(map(bool,results.values()))}/{len(results)} contrôles réussis")
raise SystemExit(0 if all(map(bool,results.values())) and len(results)>=22 else 1)
