const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),fs=require('fs'),{execFileSync}=require('child_process');
// Restauration de la démonstration : avis, demande de suppression et ville modifiés par le parcours.
const sql=q=>execFileSync(process.env.DOCKER_BIN||'docker',['compose','-f','compose.yaml','-f','compose.app.yaml','exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','innovevents_demo'],{input:'SET NAMES utf8mb4;'+q,encoding:'utf8'});
function restore(){sql("SET @u=(SELECT id FROM utilisateurs WHERE email_normalise='alice@example.invalid'); DELETE a FROM avis a JOIN evenements e ON e.id=a.evenement_id WHERE e.nom='Afterwork de rentrée Horizon' AND a.auteur_id=@u; DELETE FROM demandes_suppression WHERE utilisateur_id=@u; UPDATE clients SET ville=NULL WHERE utilisateur_id=@u;")}
// Parcours navigateur : profil et avis du client (390 px), modération par José (1280 px), publication, demande de suppression (390 px).
// Les données modifiées sont restaurées par tests/app/client-space.py (exécuté avant et après) ; ce script ne fait que naviguer.
(async()=>{const password=fs.readFileSync('.secrets/demo-password.txt','utf8').trim(),results={};
const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});fs.mkdirSync('tmp/client-space',{recursive:true});
async function login(page,address){await page.getByLabel('Adresse électronique').fill(address);await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:'Se connecter',exact:true}).click()}
const overflow=page=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
const focused=page=>page.evaluate(()=>document.activeElement?.id||'');
const text='Une croisière conviviale, une organisation sans accroc et un accueil chaleureux : merci !';
restore();
// Environnement local uniquement : remise à zéro du limiteur de connexion (10 connexions par compte en 15 minutes),
// que les essais successifs épuisent ; la limite elle-même est vérifiée par tests/app/authentication.py.
execFileSync(process.env.DOCKER_BIN||'docker',['compose','-f','compose.yaml','-f','compose.app.yaml','exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json']);
try{
 // Client à 390 px : informations personnelles avec erreur puis enregistrement.
 let context=await browser.newContext({viewport:{width:390,height:844}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/espace/informations');await login(page,'alice@example.invalid');
 await page.getByRole('heading',{name:'Mes informations'}).waitFor();
 const phone=page.getByLabel('Téléphone *'),original=await phone.inputValue();
 await phone.fill('abc');await page.getByRole('button',{name:'Enregistrer mes informations'}).click();
 await page.getByText('Saisissez un numéro de téléphone valide.').waitFor();results.profile_error_focus=await focused(page)==='profile-telephone';
 await phone.fill(original);await page.getByLabel('Ville').fill('Lyon');await page.getByRole('button',{name:'Enregistrer mes informations'}).click();
 await page.getByText('Vos informations ont été enregistrées.').waitFor();results.profile_saved_focus=await focused(page)==='profile-notice';
 results.profile_390_no_overflow=!await overflow(page);await page.screenshot({path:'tmp/client-space/profil-390.png',fullPage:true});
 // Avis sur l'événement terminé : texte trop court refusé, puis envoi.
 await page.getByRole('link',{name:'← Retour à mon espace'}).click();
 const item=page.getByRole('listitem').filter({has:page.getByRole('heading',{name:'Afterwork de rentrée Horizon'})});await item.waitFor();
 await item.getByRole('button',{name:'Donner mon avis'}).click();results.review_focus=await focused(page)==='review-text';
 await page.getByLabel('Votre avis sur « Afterwork de rentrée Horizon »').fill('Top');await page.getByRole('button',{name:'Envoyer mon avis'}).click();
 await page.getByText('Rédigez votre avis (entre 10 et 2 000 caractères).').waitFor();results.review_error_focus=await focused(page)==='review-text';
 await page.getByLabel('Votre avis sur « Afterwork de rentrée Horizon »').fill(text);await page.getByRole('button',{name:'Envoyer mon avis'}).click();
 await page.getByText('Votre avis sera publié après validation',{exact:false}).waitFor();
 results.review_pending_shown=(await item.textContent()).includes('En attente de validation');
 results.account_390_no_overflow=!await overflow(page);await page.screenshot({path:'tmp/client-space/avis-client-390.png',fullPage:true});
 results.client_no_moderation_link=await page.getByRole('link',{name:'Modérer les avis des clients'}).count()===0;
 if(errors.length)throw Error(errors.join('\n'));await context.close();

 // José (employé) à 1280 px : validation de l'avis, puis publication sur la page Avis.
 context=await browser.newContext({viewport:{width:1280,height:900}});page=await context.newPage();errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/espace');await login(page,'jose@example.invalid');
 await page.getByRole('link',{name:'Modérer les avis des clients'}).click();await page.getByRole('heading',{name:'Modération des avis'}).waitFor();
 const card=page.getByRole('listitem').filter({hasText:text});await card.waitFor();
 await card.getByRole('button',{name:'Valider et publier'}).click();await page.getByText('validé : il est publié',{exact:false}).waitFor();
 results.moderation_notice_focus=await focused(page)==='moderation-notice';
 results.moderation_queue_updated=await page.getByRole('listitem').filter({hasText:text}).count()===0;
 await page.getByRole('button',{name:'Publiés'}).click();await page.getByRole('listitem').filter({hasText:text}).waitFor();results.published_tab=(await page.getByRole('button',{name:'Valider et publier'}).count())===0;
 await page.screenshot({path:'tmp/client-space/moderation-1280.png',fullPage:true});
 await page.goto('http://127.0.0.1:5173/avis');await page.getByText(text).waitFor();results.public_after_validation=true;
 if(errors.length)throw Error(errors.join('\n'));await context.close();

 // Client à 390 px : demande de suppression avec confirmation obligatoire.
 context=await browser.newContext({viewport:{width:390,height:844}});page=await context.newPage();errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/espace/informations');await login(page,'alice@example.invalid');
 await page.getByRole('button',{name:'Envoyer la demande de suppression'}).click();
 await page.getByText('Cochez la case pour confirmer votre demande.').waitFor();results.deletion_confirm_focus=await focused(page)==='deletion-confirm';
 await page.getByLabel('Je demande la suppression totale de mes informations et de mon compte.').check();
 await page.getByRole('button',{name:'Envoyer la demande de suppression'}).click();
 await page.getByText('Demande envoyée le',{exact:false}).waitFor();results.deletion_status_focus=await focused(page)==='deletion-status';
 results.deletion_390_no_overflow=!await overflow(page);await page.screenshot({path:'tmp/client-space/suppression-390.png',fullPage:true});
 if(errors.length)throw Error(errors.join('\n'));await context.close();
}finally{await browser.close();restore();console.log(JSON.stringify(results,null,1))}
if(!Object.values(results).every(Boolean)||Object.keys(results).length<15){process.exitCode=1}
})().catch(e=>{console.error(e);process.exit(1)});
