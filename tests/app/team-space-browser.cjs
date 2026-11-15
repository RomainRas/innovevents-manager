const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),fs=require('fs'),{execFileSync}=require('child_process');
// Parcours navigateur de José (390 px) : recherche client, fiche et historique, fiche événement, note (ajout, erreur,
// modification, suppression avec annulation), tâche assignée ; Chloé (1280 px) : liste filtrée et note d'un collègue modifiable.
const compose=['compose','-f','compose.yaml','-f','compose.app.yaml'],docker=process.env.DOCKER_BIN||'docker';
const sql=q=>execFileSync(docker,[...compose,'exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],{input:'SET NAMES utf8mb4;'+q,encoding:'utf8'}).trim();
(async()=>{const password=fs.readFileSync('.secrets/demo-password.txt','utf8').trim(),results={},note=`Note navigateur ${Date.now()}`;
const sound=sql("SELECT t.id FROM taches t JOIN evenements e ON e.id=t.evenement_id WHERE e.nom='Lancement produit Horizon' AND t.titre='Réserver la sonorisation';");
const before=sql(`SELECT statut FROM taches WHERE id=${sound};`);
// Environnement local uniquement : remise à zéro du limiteur de connexion, épuisé par les essais successifs.
execFileSync(docker,[...compose,'exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json']);
const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});fs.mkdirSync('tmp/team-space',{recursive:true});
const overflow=page=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
const focused=page=>page.evaluate(()=>document.activeElement?.id||'');
const focusOn=(page,id)=>page.waitForFunction(i=>document.activeElement?.id===i,id,{timeout:5000}).then(()=>true,()=>false);
async function login(page,email){await page.getByLabel('Adresse électronique').fill(email);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:'Se connecter',exact:true}).click()}
try{
 let context=await browser.newContext({viewport:{width:390,height:844}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/espace');await login(page,'jose@example.invalid');
 await page.getByRole('link',{name:'Tous les clients'}).click();await page.getByRole('heading',{name:'Clients',exact:true}).waitFor();
 await page.getByLabel('Rechercher un client (entreprise, contact, adresse, ville)').fill('Horizon');await page.getByRole('button',{name:'Rechercher'}).click();
 await page.getByText('pour « Horizon »',{exact:false}).waitFor();results.clients_search=await page.getByRole('link',{name:'Entreprise fictive Horizon'}).count()===1;
 results.clients_390_no_overflow=!await overflow(page);
 await page.getByRole('link',{name:'Entreprise fictive Horizon'}).click();await page.getByRole('heading',{name:'Historique des événements'}).waitFor();
 results.client_history=await page.getByRole('link',{name:'Lancement produit Horizon'}).count()===1;results.client_390_no_overflow=!await overflow(page);
 await page.screenshot({path:'tmp/team-space/fiche-client-390.png',fullPage:true});
 await page.getByRole('link',{name:'Lancement produit Horizon'}).click();await page.getByRole('heading',{name:'Tâches'}).waitFor();
 // Tâche assignée : avancement d'un statut.
 const task=page.getByRole('listitem').filter({has:page.getByRole('heading',{name:/Réserver la sonorisation/})});
 await task.getByRole('button',{name:'Commencer la tâche'}).click();results.task_focus=await focusOn(page,'team-notice');
 results.task_started=(await task.textContent()).includes('En cours')&&await task.getByRole('button',{name:'Marquer comme terminée'}).count()===1;
 results.other_task_no_button=await page.getByRole('listitem').filter({has:page.getByRole('heading',{name:/Envoyer le programme au client/})}).getByRole('button').count()===0;
 // Note : erreur, ajout, modification, suppression annulée puis confirmée.
 await page.getByRole('button',{name:'Ajouter la note'}).click();await page.getByText('Rédigez la note avant de l’ajouter.').waitFor();results.note_error_focus=await focused(page)==='note-new';
 await page.getByLabel('Ajouter une note').fill(note);await page.getByRole('button',{name:'Ajouter la note'}).click();
 await page.getByText('Note ajoutée.').waitFor();results.note_added_focus=await focusOn(page,'team-notice');
 const item=page.getByRole('listitem').filter({hasText:note});await item.waitFor();
 await item.getByRole('button',{name:'Modifier'}).click();results.note_edit_focus=(await focused(page)).startsWith('note-edit-');
 // En modification, le texte est dans le champ : cibler le champ et le bouton de la page.
 await page.getByLabel('Modifier la note').fill(note+' (corrigée)');await page.getByRole('button',{name:'Enregistrer'}).click();
 await page.getByText('Note modifiée.').waitFor();results.note_edited=(await page.getByRole('listitem').filter({hasText:note+' (corrigée)'}).textContent()).includes('(modifiée)');
 await page.screenshot({path:'tmp/team-space/fiche-evenement-390.png',fullPage:true});results.event_390_no_overflow=!await overflow(page);
 if(errors.length)throw Error(errors.join('\n'));await context.close();

 // Chloé : filtre des événements, note de José modifiable puis supprimée.
 context=await browser.newContext({viewport:{width:1280,height:900}});page=await context.newPage();errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/equipe/evenements');await login(page,'chloe@example.invalid');await page.getByRole('heading',{name:'Événements',exact:true}).waitFor();
 await page.getByLabel('Statut').selectOption('en_cours');await page.waitForFunction(()=>[...document.querySelectorAll('.badge')].every(b=>b.textContent.trim()==='En cours')&&document.querySelectorAll('.badge').length>0,null,{timeout:10000});
 results.events_filter=true;await page.screenshot({path:'tmp/team-space/evenements-1280.png',fullPage:true});
 await page.getByRole('link',{name:'Lancement produit Horizon'}).click();
 const joseNote=page.getByRole('listitem').filter({hasText:note+' (corrigée)'});await joseNote.waitFor();
 results.admin_can_manage_note=await joseNote.getByRole('button',{name:'Modifier'}).count()===1;
 results.admin_no_task_button_for_jose=await page.getByRole('listitem').filter({has:page.getByRole('heading',{name:/Réserver la sonorisation/})}).getByRole('button').count()===0;
 await joseNote.getByRole('button',{name:'Supprimer…'}).click();await joseNote.getByRole('button',{name:'Annuler'}).click();
 results.delete_cancel_focus=(await focused(page)).startsWith('note-actions-');
 await joseNote.getByRole('button',{name:'Supprimer…'}).click();await joseNote.getByRole('button',{name:'Supprimer la note'}).click();
 await page.getByText('Note supprimée.').waitFor();results.note_deleted=await page.getByRole('listitem').filter({hasText:note}).count()===0;
 results.event_1280_no_overflow=!await overflow(page);
 if(errors.length)throw Error(errors.join('\n'));await context.close();
}finally{await browser.close();
 sql(`DELETE FROM notes_evenement WHERE contenu LIKE 'Note navigateur %';`);if(/^\d+$/.test(sound)&&/^[a-z_]+$/.test(before))sql(`UPDATE taches SET statut='${before}' WHERE id=${sound};`);
 execFileSync(docker,[...compose,'exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json']);console.log(JSON.stringify(results,null,1))}
if(!Object.values(results).every(Boolean)||Object.keys(results).length<18)process.exitCode=1;
})().catch(e=>{console.error(e);process.exit(1)});
