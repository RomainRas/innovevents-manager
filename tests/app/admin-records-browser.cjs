const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),fs=require('fs'),{execFileSync}=require('child_process');
// Parcours navigateur de Chloé (1280 px puis 390 px) : création et modification d'une fiche client, création d'un
// événement, changement de statut, tâche ajoutée puis supprimée, modification, suppression de l'événement et de la fiche.
const compose=['compose','-f','compose.yaml','-f','compose.app.yaml'],docker=process.env.DOCKER_BIN||'docker',BASE='http://127.0.0.1:5173';
const sql=q=>execFileSync(docker,[...compose,'exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],{input:'SET NAMES utf8mb4;'+q,encoding:'utf8'}).trim();
(async()=>{const password=fs.readFileSync('.secrets/demo-password.txt','utf8').trim(),results={},tag=Date.now().toString(36),company=`Navigateur ${tag}`;
execFileSync(docker,[...compose,'exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json']);
const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});fs.mkdirSync('tmp/admin-records',{recursive:true});
const overflow=page=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
const focusOn=(page,id)=>page.waitForFunction(i=>document.activeElement?.id===i,id,{timeout:5000}).then(()=>true,()=>false);
async function login(page){await page.getByLabel('Adresse électronique').fill('chloe@example.invalid');await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:'Se connecter',exact:true}).click()}
try{
 let context=await browser.newContext({viewport:{width:1280,height:900}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(BASE+'/equipe/clients');await login(page);await page.getByRole('heading',{name:'Clients',exact:true}).waitFor();
 // Fiche client : erreur puis création.
 await page.getByRole('button',{name:'Nouveau client'}).click();results.create_form_focus=await focusOn(page,'client-new-entreprise');
 await page.getByRole('button',{name:'Créer la fiche client'}).click();await page.getByText('Vérifiez les champs signalés.').waitFor();results.create_error_focus=await focusOn(page,'client-new-entreprise');
 await page.getByLabel('Entreprise *',{exact:true}).fill(company);await page.getByLabel('Prénom du contact *').fill('Noé');await page.getByLabel('Nom du contact *',{exact:true}).fill('Durand');
 await page.getByLabel('Adresse électronique *').fill(`noe-${tag}@example.invalid`);await page.getByLabel('Téléphone *').fill('01 23 45 67 89');
 await page.getByRole('button',{name:'Créer la fiche client'}).click();await page.getByText('Fiche client créée.').waitFor();
 results.client_page=await page.getByRole('heading',{name:company}).count()===1;
 // Modification de la fiche.
 await page.getByRole('button',{name:'Modifier la fiche'}).click();results.edit_focus=await focusOn(page,'client-edit-entreprise');
 await page.getByLabel('Ville',{exact:true}).fill('Grenoble');await page.getByRole('button',{name:'Enregistrer la fiche'}).click();
 await page.getByText('Fiche client enregistrée.').waitFor();results.client_edited=(await page.locator('.facts').textContent()).includes('Grenoble');
 // Événement créé pour ce client.
 await page.getByRole('link',{name:'Nouvel événement pour ce client'}).click();await page.getByRole('heading',{name:'Nouvel événement'}).waitFor();
 results.client_preselected=await page.getByLabel('Client *').inputValue()!=='';
 await page.getByLabel('Nom de l’événement *').fill(`Séminaire ${tag}`);await page.getByLabel('Lieu',{exact:true}).fill('Salle fictive, Grenoble');
 await page.getByLabel('Début').fill('2027-04-15T09:00');await page.getByLabel('Fin').fill('2027-04-15T17:00');await page.getByLabel('Participants estimés').fill('40');
 await page.getByRole('button',{name:'Créer l’événement (brouillon)'}).click();await page.getByText('Événement créé (brouillon).').waitFor();
 results.event_created=(await page.locator('.facts').first().textContent()).includes('Brouillon');
 // Statut journalisé.
 await page.getByLabel('Nouveau statut').selectOption('accepte');await page.getByRole('button',{name:'Changer le statut'}).click();
 await page.getByText('Le changement est enregistré dans le journal.',{exact:false}).waitFor();results.status_focus=await focusOn(page,'admin-notice');
 await page.waitForFunction(()=>document.querySelector('.facts')?.textContent.includes('Accepté'),null,{timeout:5000});results.status_shown=true;
 const eventId=page.url().split('/').pop().split('?')[0];
 results.status_logged=sql(`SELECT COUNT(*) FROM journal_a_emettre WHERE type_action='CHANGEMENT_STATUT_EVENEMENT' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.id_evenement'))='${eventId}';`)==='1';
 // Tâche assignée à José, puis supprimée.
 await page.getByLabel('Titre *',{exact:true}).fill('Imprimer les badges');await page.getByLabel('Assignée à').selectOption({label:'José Démo'});
 await page.getByRole('button',{name:'Ajouter la tâche'}).click();await page.getByText('Tâche « Imprimer les badges » ajoutée.').waitFor();
 const task=page.getByRole('listitem').filter({has:page.getByRole('heading',{name:/Imprimer les badges/})});await task.waitFor();
 results.task_added=(await task.textContent()).includes('José');
 await task.getByRole('button',{name:'Supprimer la tâche'}).click();await page.getByText('Tâche « Imprimer les badges » supprimée.').waitFor();
 results.task_removed=await page.getByRole('heading',{name:/Imprimer les badges/}).count()===0;
 // Modification de l'événement.
 await page.getByRole('link',{name:'Modifier les informations'}).click();await page.getByRole('heading',{name:'Modifier l’événement'}).waitFor();
 results.edit_prefilled=await page.getByLabel('Début').inputValue()==='2027-04-15T09:00';
 await page.getByLabel('Participants estimés').fill('55');await page.getByRole('button',{name:'Enregistrer les modifications'}).click();
 await page.getByText('Événement modifié.').waitFor();results.event_edited=(await page.locator('.facts').first().textContent()).includes('55');
 await page.screenshot({path:'tmp/admin-records/evenement-1280.png',fullPage:true});results.event_1280_no_overflow=!await overflow(page);
 if(errors.length)throw Error(errors.join('\n'));await context.close();

 // 390 px : événement avec devis protégé, puis suppressions.
 context=await browser.newContext({viewport:{width:390,height:844}});page=await context.newPage();errors=[];page.on('pageerror',e=>errors.push(e.message));
 const quoted=sql("SELECT e.id FROM evenements e JOIN devis d ON d.evenement_id=e.id WHERE e.nom='Lancement produit Horizon' LIMIT 1;")||sql('SELECT evenement_id FROM devis LIMIT 1;');
 await page.goto(BASE+'/equipe/evenements/'+quoted);await login(page);await page.getByRole('heading',{name:'Gestion de l’événement'}).waitFor();
 await page.getByText('Cet événement a un devis : il ne peut pas être supprimé',{exact:false}).waitFor();
 results.quoted_event_protected=await page.getByRole('button',{name:'Supprimer l’événement…'}).count()===0;
 results.panel_390_no_overflow=!await overflow(page);await page.screenshot({path:'tmp/admin-records/gestion-390.png',fullPage:true});
 await page.goto(BASE+'/equipe/evenements/'+eventId);await page.getByRole('button',{name:'Supprimer l’événement…'}).click();
 results.delete_dialog_focus=await focusOn(page,'admin-delete-dialog');
 await page.getByRole('button',{name:'Supprimer définitivement'}).click();await page.getByText('Événement supprimé.').waitFor();results.event_deleted=true;
 const clientId=sql(`SELECT id FROM clients WHERE entreprise='${company}';`);
 await page.goto(BASE+'/equipe/clients/'+clientId);await page.getByRole('button',{name:'Supprimer la fiche…'}).click();
 await page.getByRole('button',{name:'Supprimer définitivement'}).click();await page.getByText('Fiche client supprimée.').waitFor();
 results.client_deleted=sql(`SELECT COUNT(*) FROM clients WHERE entreprise='${company}';`)==='0';
 if(errors.length)throw Error(errors.join('\n'));await context.close();
}finally{await browser.close();
 const ids=sql(`SELECT GROUP_CONCAT(e.id) FROM evenements e JOIN clients c ON c.id=e.client_id WHERE c.entreprise='${company}';`);
 if(/^[\d,]+$/.test(ids))sql(`DELETE FROM taches WHERE evenement_id IN (${ids}); DELETE FROM notes_evenement WHERE evenement_id IN (${ids}); DELETE FROM evenements WHERE id IN (${ids});`);
 sql(`DELETE FROM clients WHERE entreprise='${company}';`);
 execFileSync(docker,[...compose,'exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json']);console.log(JSON.stringify(results,null,1))}
if(!Object.values(results).every(Boolean)||Object.keys(results).length<20)process.exitCode=1;
})().catch(e=>{console.error(e);process.exit(1)});
