"""Contrôles HTTP de la gestion des fiches par l'administratrice : clients, événements, statuts, tâches et journal.
Les fiches créées pour l'essai sont supprimées par l'application elle-même (puis en base en cas d'échec)."""
from pathlib import Path
import http.cookiejar, json, os, secrets, subprocess, time, urllib.request, urllib.error
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
    def call(self,path,data=None,csrf=True):
        req=urllib.request.Request(BASE+path,data=json.dumps(data).encode() if data is not None else None,headers={'Origin':BASE,'Content-Type':'application/json','X-CSRF-Token':self.csrf if csrf else ''})
        try:
            with self.client.open(req,timeout=30) as r:s,d=r.status,json.loads(r.read())
        except urllib.error.HTTPError as e:s,d=e.code,json.loads(e.read())
        if 'csrf' in d:self.csrf=d['csrf']
        return s,d
    def login(self,address):return self.call('/api/auth/login',{'email':address,'password':(ROOT/'.secrets/demo-password.txt').read_text().strip()})
def journal(kind,key,value):
    return sql(f"SELECT CONCAT(etat,'|',details) FROM journal_a_emettre WHERE type_action='{kind}' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.{key}'))='{value}' ORDER BY cree_le DESC LIMIT 1;")
def delivered(kind,key,value,timeout=30):
    for _ in range(timeout):
        if journal(kind,key,value).startswith('livre|'):return True
        time.sleep(1)
    return False
subprocess.run(COMPOSE+['exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json'],cwd=ROOT,check=True)
tag=secrets.token_hex(3);company=f'Essai gestion {tag}';client_id=event_id=empty_event=None
try:
    anonymous=Browser();jose=Browser();chloe=Browser()
    assert jose.login('jose@example.invalid')[0]==200 and chloe.login('chloe@example.invalid')[0]==200
    jose_id=sql("SELECT id FROM utilisateurs WHERE email_normalise='jose@example.invalid';");alice_id=sql("SELECT id FROM utilisateurs WHERE email_normalise='alice@example.invalid';")
    results['admin_only']=(anonymous.call('/api/admin/client-create',{})[0]==401 and jose.call('/api/admin/client-create',{'entreprise':'X'})[0]==403
        and jose.call('/api/admin/event-status',{'id':'1','statut':'annule'})[0]==403 and jose.call('/api/admin/records-context')[0]==403)
    results['csrf_required']=chloe.call('/api/admin/client-create',{'entreprise':'X'},csrf=False)[0]==403

    # Clients : création, modification, journal.
    client={'entreprise':company,'contact_prenom':'Léa','contact_nom':'Martin','email':f'lea-{tag}@example.invalid','telephone':'04 72 00 00 00','adresse_ligne1':'3 rue fictive','code_postal':'69001','ville':'Lyon'}
    code,data=chloe.call('/api/admin/client-create',{**client,'email':'pas-une-adresse','telephone':'abc','entreprise':''})
    results['client_validation']=code==422 and set(data['fields'])=={'entreprise','email','telephone'}
    code,data=chloe.call('/api/admin/client-create',client);client_id=data.get('id')
    results['client_created']=code==201 and sql(f"SELECT entreprise FROM clients WHERE id={client_id};")==company
    results['client_creation_logged']=f'"nom_client": "{company}"' in journal('CREATION_CLIENT','id_client',client_id)
    results['client_listed_for_team']=client_id in [c['id'] for c in jose.call('/api/team/clients?q='+tag)[1]['clients']]
    code,_=chloe.call('/api/admin/client-save',{**client,'id':client_id,'ville':'Villeurbanne','code_postal':'69100'})
    results['client_updated']=code==200 and sql(f"SELECT ville FROM clients WHERE id={client_id};")=='Villeurbanne'
    results['client_update_logged']=journal('MODIFICATION_CLIENT','id_client',client_id)!=''
    results['client_unknown']=chloe.call('/api/admin/client-save',{**client,'id':'999999999'})[0]==404

    # Événements : création sans demande préalable, modification avec révision, statut journalisé.
    event={'client_id':client_id,'nom':f'Gala essai {tag}','debut':'2027-03-10T18:00','fin':'2027-03-10T23:30','lieu':'Salle fictive, Lyon','type_evenement':'Soirée d’entreprise','theme':'Gala','participants_estimes':'120'}
    results['event_dates_checked']=chloe.call('/api/admin/event-new',{**event,'fin':'2027-03-10T17:00'})[0]==422
    results['event_client_checked']=chloe.call('/api/admin/event-new',{**event,'client_id':'999999999'})[0]==404
    code,data=chloe.call('/api/admin/event-new',event);event_id=data.get('id');gala_id=event_id
    results['event_created_draft']=code==201 and sql(f"SELECT CONCAT(statut,'|',participants_estimes,'|',theme) FROM evenements WHERE id={event_id};")=='brouillon|120|Gala'
    results['event_creation_logged']=f'"nom_evenement": "Gala essai {tag}"' in journal('CREATION_EVENEMENT','id_evenement',event_id)
    code,data=chloe.call('/api/admin/event-edit?id='+event_id);ev=data.get('evenement',{})
    results['event_edit_context']=code==200 and ev['debut']=='2027-03-10T18:00' and any(p['id']==jose_id for p in data['equipe']) and not any(p['id']==alice_id for p in data['equipe'])
    code,data=chloe.call('/api/admin/event-update',{**event,'id':event_id,'revision':str(ev['revision']),'participants_estimes':'150'})
    results['event_updated']=code==200 and data['evenement']['participants_estimes']==150 and data['evenement']['revision']==ev['revision']+1
    results['event_stale_revision']=chloe.call('/api/admin/event-update',{**event,'id':event_id,'revision':str(ev['revision'])})[0]==409
    results['status_invalid']=chloe.call('/api/admin/event-status',{'id':event_id,'statut':'brouillon'})[0]==422
    code,data=chloe.call('/api/admin/event-status',{'id':event_id,'statut':'accepte'})
    results['status_accepted']=code==200 and data['evenement']['statut']=='accepte'
    entry=journal('CHANGEMENT_STATUT_EVENEMENT','id_evenement',event_id)
    results['status_logged']='"ancien_statut": "brouillon"' in entry and '"nouveau_statut": "accepte"' in entry
    results['status_unchanged']=chloe.call('/api/admin/event-status',{'id':event_id,'statut':'accepte'})[0]==409
    chloe.call('/api/admin/event-status',{'id':event_id,'statut':'en_cours'})
    results['status_in_progress_visible']=jose.call('/api/team/event?id='+event_id)[1]['evenement']['statut']=='en_cours'
    code,data=chloe.call('/api/admin/event-new',{'client_id':client_id,'nom':f'Sans date {tag}'});empty_event=data.get('id')
    results['status_requires_dates']=code==201 and chloe.call('/api/admin/event-status',{'id':empty_event,'statut':'accepte'})[0]==422

    # Tâches : création assignée, refus d'un client comme responsable, suppression.
    results['task_assignee_checked']=chloe.call('/api/admin/task-create',{'evenement_id':event_id,'titre':'Tâche invalide','assigne_a':alice_id})[0]==422
    code,data=chloe.call('/api/admin/task-create',{'evenement_id':event_id,'titre':'Réserver le vestiaire','description':'Prévoir 150 places.','assigne_a':jose_id});task=data.get('id')
    tasks={t['id']:t for t in jose.call('/api/team/event?id='+event_id)[1]['taches']}
    results['task_created_for_jose']=code==201 and tasks[task]['a_moi'] is True and tasks[task]['suivant']=='en_cours'
    code,data=chloe.call('/api/admin/task-create',{'evenement_id':event_id,'titre':'Tâche libre','assigne_a':''})
    results['task_unassigned']=code==201
    results['task_deleted']=chloe.call('/api/admin/task-delete',{'id':task})[0]==200 and chloe.call('/api/admin/task-delete',{'id':task})[0]==404

    # Annulation : l'événement annulé n'est plus modifiable.
    chloe.call('/api/admin/event-status',{'id':event_id,'statut':'annule'})
    results['cancelled_frozen']=(chloe.call('/api/admin/event-status',{'id':event_id,'statut':'en_cours'})[0]==409
        and chloe.call('/api/admin/event-update',{**event,'id':event_id,'revision':'99'})[0]==409
        and chloe.call('/api/admin/task-create',{'evenement_id':event_id,'titre':'Trop tard'})[0]==409)

    # Suppressions protégées : devis, événements, compte.
    with_quote=sql('SELECT evenement_id FROM devis LIMIT 1;')
    results['event_with_quote_kept']=chloe.call('/api/admin/event-delete',{'id':with_quote,'confirmation':True})[0]==409
    alice_client=sql(f"SELECT id FROM clients WHERE utilisateur_id={alice_id};")
    results['client_with_account_kept']=chloe.call('/api/admin/client-delete',{'id':alice_client,'confirmation':True})[0]==409
    results['client_with_events_kept']=chloe.call('/api/admin/client-delete',{'id':client_id,'confirmation':True})[0]==409
    results['delete_needs_confirmation']=chloe.call('/api/admin/event-delete',{'id':event_id})[0]==422
    jose.call('/api/team/note-save',{'evenement_id':empty_event,'contenu':'Note à supprimer avec l’événement.'})
    results['event_deleted_with_notes']=(chloe.call('/api/admin/event-delete',{'id':event_id,'confirmation':True})[0]==200
        and chloe.call('/api/admin/event-delete',{'id':empty_event,'confirmation':True})[0]==200
        and sql(f"SELECT COUNT(*) FROM notes_evenement WHERE evenement_id IN ({event_id},{empty_event});")=='0'
        and sql(f"SELECT COUNT(*) FROM taches WHERE evenement_id={event_id};")=='0')
    event_id=empty_event=None
    code,_=chloe.call('/api/admin/client-delete',{'id':client_id,'confirmation':True})
    results['client_deleted']=code==200 and sql(f"SELECT COUNT(*) FROM clients WHERE id={client_id};")=='0'
    results['client_deletion_logged']=f'"nom_client": "{company}"' in journal('SUPPRESSION_CLIENT','id_client',client_id)
    # Le traitement du journal accepte les entrées : elles sont livrées dans MongoDB (schéma validé).
    results['journal_delivered_to_mongodb']=delivered('SUPPRESSION_CLIENT','id_client',client_id) and delivered('CHANGEMENT_STATUT_EVENEMENT','id_evenement',gala_id) and delivered('CREATION_EVENEMENT','id_evenement',gala_id)
    deleted_client=client_id;client_id=None
    results['sql_no_quote_write']='devis:UPDATE' not in sql("SELECT GROUP_CONCAT(CONCAT(TABLE_NAME,':',PRIVILEGE_TYPE)) FROM information_schema.COLUMN_PRIVILEGES WHERE GRANTEE=\"'ecf_records'@'%'\";")
finally:
    for e in (event_id,empty_event):
        if e:sql(f"DELETE FROM notes_evenement WHERE evenement_id={e}; DELETE FROM taches WHERE evenement_id={e}; DELETE FROM evenements WHERE id={e};")
    if client_id:sql(f"DELETE FROM evenements WHERE client_id={client_id} AND id NOT IN (SELECT evenement_id FROM devis); DELETE FROM clients WHERE id={client_id};")
    subprocess.run(COMPOSE+['exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json'],cwd=ROOT)
for k,v in results.items():print(('OK  ' if v else 'ÉCHEC ')+k)
print(f"{sum(map(bool,results.values()))}/{len(results)} contrôles réussis")
raise SystemExit(0 if all(map(bool,results.values())) and len(results)>=36 else 1)
