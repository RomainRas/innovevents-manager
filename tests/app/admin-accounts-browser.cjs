const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),fs=require('fs'),{execFileSync}=require('child_process');
// Parcours navigateur de Chloé : tableau de bord (1280 px), création d'un employé avec erreur, suspension, réactivation,
// suppression avec confirmation (390 px). Le compte créé est retiré en fin d'essai.
const compose=['compose','-f','compose.yaml','-f','compose.app.yaml'],docker=process.env.DOCKER_BIN||'docker';
const sql=q=>execFileSync(docker,[...compose,'exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],{input:'SET NAMES utf8mb4;'+q,encoding:'utf8'}).trim();
(async()=>{const password=fs.readFileSync('.secrets/demo-password.txt','utf8').trim(),results={},email=`navigateur-${Date.now()}@example.invalid`;let createdId='';
// Environnement local uniquement : remise à zéro du limiteur de connexion, épuisé par les essais successifs.
execFileSync(docker,[...compose,'exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json']);
const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});fs.mkdirSync('tmp/admin-accounts',{recursive:true});
const overflow=page=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
const focused=page=>page.evaluate(()=>document.activeElement?.id||'');
async function login(page){await page.getByLabel('Adresse électronique').fill('chloe@example.invalid');await page.getByLabel('Mot de passe',{exact:true}).fill(password);await page.getByRole('button',{name:'Se connecter',exact:true}).click()}
try{
 let context=await browser.newContext({viewport:{width:1280,height:900}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/espace');await login(page);
 await page.getByRole('heading',{name:'Tableau de bord'}).waitFor();
 const upcoming=page.getByRole('region',{name:'Prochains événements'});await upcoming.getByRole('listitem').first().waitFor();
 results.dashboard_three_events=await upcoming.getByRole('listitem').count()<=3&&await upcoming.getByRole('listitem').count()>0;
 results.dashboard_widgets=await page.getByRole('region',{name:'Notes récentes'}).count()===1&&(await page.getByRole('region',{name:'Indicateurs clés'}).textContent()).includes('Clients actifs');
 await page.screenshot({path:'tmp/admin-accounts/tableau-de-bord-1280.png',fullPage:true});results.dashboard_1280_no_overflow=!await overflow(page);
 // Création d'un employé : erreur de saisie puis succès.
 await page.getByRole('link',{name:'Gérer les comptes employés et clients'}).click();await page.getByRole('heading',{name:'Comptes employés et clients'}).waitFor();
 await page.getByLabel('Prénom *').fill('Nina');await page.getByLabel('Nom *',{exact:true}).fill('Navigateur');await page.getByLabel('Adresse électronique *').fill('invalide');
 await page.getByRole('button',{name:'Créer le compte et envoyer les identifiants'}).click();
 await page.getByText('Saisissez une adresse électronique valide.').waitFor();results.create_error_focus=await focused(page)==='create-email';
 await page.getByLabel('Adresse électronique *').fill(email);await page.getByLabel('Accès à l’application mobile').check();
 await page.getByRole('button',{name:'Créer le compte et envoyer les identifiants'}).click();
 await page.getByText('Les identifiants lui ont été envoyés',{exact:false}).waitFor();
 // Le focus est placé après le rechargement de la liste.
 await page.waitForFunction(()=>document.activeElement?.id==='accounts-notice',null,{timeout:5000}).catch(()=>{});results.create_notice_focus=await focused(page)==='accounts-notice';
 await page.getByLabel('Rechercher (nom, adresse, entreprise)').fill('Navigateur');await page.getByRole('button',{name:'Rechercher'}).click();
 const card=page.getByRole('listitem').filter({hasText:email});await card.waitFor();
 results.created_listed=(await card.textContent()).includes('accès mobile');createdId=sql(`SELECT id FROM utilisateurs WHERE email_normalise='${email}';`);
 if(errors.length)throw Error(errors.join('\n'));await context.close();
 // Suspension, réactivation et suppression à 390 px.
 context=await browser.newContext({viewport:{width:390,height:844}});page=await context.newPage();errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/comptes');await login(page);await page.getByRole('heading',{name:'Comptes employés et clients'}).waitFor();
 await page.getByLabel('Rechercher (nom, adresse, entreprise)').fill('Navigateur');await page.getByRole('button',{name:'Rechercher'}).click();
 let item=page.getByRole('listitem').filter({hasText:email});await item.waitFor();
 await item.getByRole('button',{name:'Suspendre'}).click();await page.getByText('suspendu : la connexion est bloquée',{exact:false}).waitFor();
 item=page.getByRole('listitem').filter({hasText:email});await item.getByText('Suspendu',{exact:true}).waitFor();results.suspended_badge=await item.getByRole('button',{name:'Réactiver'}).count()===1;
 results.suspended_in_db=sql(`SELECT CONCAT(statut,acces_mobile) FROM utilisateurs WHERE email_normalise='${email}';`)==='suspendu0';
 await item.getByRole('button',{name:'Réactiver'}).click();await page.getByText('réactivé',{exact:false}).waitFor();
 item=page.getByRole('listitem').filter({hasText:email});
 await item.getByRole('button',{name:'Supprimer…'}).click();results.delete_dialog_focus=await focused(page)==='delete-dialog';
 await item.getByRole('button',{name:'Annuler'}).click();results.delete_cancel_focus=(await focused(page)).startsWith('delete-');
 await item.getByRole('button',{name:'Supprimer…'}).click();await page.screenshot({path:'tmp/admin-accounts/suppression-390.png',fullPage:true});
 results.accounts_390_no_overflow=!await overflow(page);
 await item.getByRole('button',{name:'Supprimer définitivement'}).click();await page.getByText('informations personnelles effacées',{exact:false}).waitFor();
 results.deleted_not_listed=await page.getByRole('listitem').filter({hasText:email}).count()===0;
 results.deleted_in_db=sql(`SELECT CONCAT(email_normalise,'|',supprime_le IS NOT NULL) FROM utilisateurs WHERE id=${createdId};`)===`supprime-${createdId}@supprime.invalid|1`;
 if(errors.length)throw Error(errors.join('\n'));await context.close();
}finally{await browser.close();
 if(/^\d+$/.test(createdId))sql(`DELETE FROM utilisateurs WHERE id=${createdId};`);
 execFileSync(docker,[...compose,'exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json']);console.log(JSON.stringify(results,null,1))}
if(!Object.values(results).every(Boolean)||Object.keys(results).length<13)process.exitCode=1;
})().catch(e=>{console.error(e);process.exit(1)});
