const {chromium,devices}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),fs=require('fs'),{execFileSync}=require('child_process');
// Parcours de l'application mobile sur un téléphone émulé (Pixel 7, tactile) : connexion de José, tableau de bord,
// fiche événement, note rapide, fiche contact (appel, courriel, itinéraire), consultation hors ligne, déconnexion
// avec effacement des données, refus d'un client. La note créée est supprimée en fin d'essai.
const compose=['compose','-f','compose.yaml','-f','compose.app.yaml'],docker=process.env.DOCKER_BIN||'docker',BASE='http://127.0.0.1:5173';
const sql=q=>execFileSync(docker,[...compose,'exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],{input:'SET NAMES utf8mb4;'+q,encoding:'utf8'}).trim();
(async()=>{const password=fs.readFileSync('.secrets/demo-password.txt','utf8').trim(),results={},note=`Note mobile ${Date.now()}`;
execFileSync(docker,[...compose,'exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json']);
const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});fs.mkdirSync('tmp/mobile',{recursive:true});
const overflow=page=>page.evaluate(()=>document.documentElement.scrollWidth>innerWidth);
async function login(page,email){await page.getByLabel('Adresse électronique').fill(email);await page.getByLabel('Mot de passe').fill(password);await page.getByRole('button',{name:'Se connecter'}).click()}
try{
 const context=await browser.newContext({...devices['Pixel 7']}),page=await context.newPage(),errors=[];page.on('pageerror',e=>{/* Client de rechargement de Vite (développement uniquement) : connexion WebSocket perdue hors ligne. */if(!/WebSocket closed without opened/.test(e.message))errors.push(e.message)});
 await page.goto(BASE+'/mobile/');await page.getByRole('heading',{name:'Connexion'}).waitFor();
 results.legal_links=await page.getByRole('link',{name:'Conditions générales d’utilisation'}).count()===1&&await page.getByRole('link',{name:'Conditions générales de vente'}).count()===1;
 await page.getByRole('button',{name:'Se connecter'}).click();results.login_error=await page.evaluate(()=>document.activeElement?.id)==='login-error';
 await login(page,'jose@example.invalid');await page.getByRole('heading',{name:'Événements à venir'}).waitFor();
 // Service worker actif, puis rechargement en ligne pour mettre l'application en cache (premier usage).
 results.service_worker=await page.evaluate(async()=>{await navigator.serviceWorker.ready;return !!navigator.serviceWorker.controller||(await new Promise(r=>{navigator.serviceWorker.addEventListener('controllerchange',()=>r(true));setTimeout(()=>r(false),5000)}))});
 await page.reload();await page.getByRole('heading',{name:'Événements à venir'}).waitFor();
 const cards=page.locator('.event-card');await cards.first().waitFor();
 results.dashboard_list=await cards.count()>3;results.dashboard_no_overflow=!await overflow(page);
 results.manifest_linked=await page.evaluate(()=>document.querySelector('link[rel=manifest]')?.getAttribute('href'))==='/mobile/manifest.webmanifest';
 await page.screenshot({path:'tmp/mobile/1-tableau-de-bord.png'});
 // Fiche événement : informations clés et bouton « Ajouter une note » visible sans défiler.
 await page.getByRole('link',{name:/Lancement produit Horizon/}).tap();await page.getByRole('heading',{name:'Lancement produit Horizon'}).waitFor();
 const facts=await page.locator('.facts').textContent();results.event_key_info=facts.includes('Rooftop')&&facts.includes('28/01/2027')&&facts.includes('Entreprise fictive Horizon');
 const add=page.getByRole('button',{name:'＋ Ajouter une note'});const box=await add.boundingBox();results.note_button_visible=!!box&&box.y+box.height<=page.viewportSize().height;
 await add.tap();results.note_focus=await page.evaluate(()=>document.activeElement?.id)==='note-text';
 await page.getByRole('button',{name:'Enregistrer'}).tap();results.note_empty_error=await page.getByText('Saisissez la note avant de l’enregistrer.').count()===1;
 await page.getByLabel(/Note sur/).fill(note);await page.getByRole('button',{name:'Enregistrer'}).tap();
 await page.getByText('Note enregistrée.').waitFor();results.note_saved=(await page.locator('.notes').textContent()).includes(note);
 results.event_no_overflow=!await overflow(page);await page.screenshot({path:'tmp/mobile/2-fiche-evenement.png',fullPage:true});
 // Fiche contact : actions en un geste.
 await page.locator('.client-link').tap();await page.getByRole('link',{name:/Appeler/}).waitFor();
 const href=name=>page.getByRole('link',{name}).getAttribute('href');
 results.call_link=/^tel:\+?[0-9]{6,}$/.test(await href(/Appeler/));
 results.mail_link=(await href(/Envoyer un courriel/)).startsWith('mailto:alice@');
 const maps=await href(/Itinéraire/);results.route_link=maps.startsWith('https://www.google.com/maps/dir/?api=1&destination=')&&decodeURIComponent(maps).includes('69003 Lyon');
 results.contact_no_overflow=!await overflow(page);await page.screenshot({path:'tmp/mobile/3-fiche-contact.png'});
 await page.getByRole('link',{name:'← Retour à l’événement'}).tap();await page.getByRole('heading',{name:'Lancement produit Horizon'}).waitFor();
 // Hors ligne : la dernière liste et la fiche consultée restent lisibles, l'ajout de note est désactivé.
 await page.goto(BASE+'/mobile/#/');await page.locator('.event-card').first().waitFor();
 await context.setOffline(true);await page.reload();
 await page.getByText('Hors ligne : dernières informations enregistrées sur ce téléphone.').waitFor();
 results.offline_list=await page.locator('.event-card').count()>3;await page.screenshot({path:'tmp/mobile/4-hors-ligne.png'});
 await page.getByRole('link',{name:/Lancement produit Horizon/}).tap();await page.getByRole('heading',{name:'Lancement produit Horizon'}).waitFor();
 results.offline_event=(await page.locator('.notes').textContent()).includes(note);
 results.offline_note_disabled=await page.getByRole('button',{name:'＋ Ajouter une note'}).isDisabled();
 await context.setOffline(false);
 // Déconnexion : données client effacées du téléphone.
 await page.getByRole('button',{name:'Se déconnecter'}).tap();await page.getByRole('heading',{name:'Connexion'}).waitFor();
 results.logout_clears_data=await page.waitForFunction(async()=>!(await caches.keys()).includes('ie-mobile-data'),null,{timeout:5000}).then(()=>true,()=>false);
 // Un client n'a pas accès à l'application mobile.
 await login(page,'alice@example.invalid');await page.getByRole('heading',{name:'Accès réservé'}).waitFor();results.client_refused=true;
 if(errors.length)throw Error(errors.join('\n'));await context.close();
}finally{await browser.close();sql(`DELETE FROM notes_evenement WHERE contenu LIKE 'Note mobile %';`);
 execFileSync(docker,[...compose,'exec','-T','api','rm','-f','/var/lib/innovevents/login-rate.json']);console.log(JSON.stringify(results,null,1))}
if(!Object.values(results).every(Boolean)||Object.keys(results).length<21)process.exitCode=1;
})().catch(e=>{console.error(e);process.exit(1)});
