const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),fs=require('fs');
// Parcours navigateur : demande de modification avec réponse perdue (1280 px), contre-proposition, acceptation avec annulation (390 px).
(async()=>{const ids=(process.env.DECISION_TEST_IDS||'').split(','),email=process.env.DECISION_TEST_EMAIL,password=process.env.DECISION_TEST_PASSWORD;
if(ids.length!==2||!ids.every(x=>/^\d+$/.test(x))||!email||!password)throw Error('Fixtures');
const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});fs.mkdirSync('tmp/quote-decisions',{recursive:true});
async function login(page,address,secret){await page.getByLabel('Adresse électronique').fill(address);await page.getByLabel('Mot de passe',{exact:true}).fill(secret);await page.getByRole('button',{name:'Se connecter',exact:true}).click()}
async function check(page,errors,label){if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Horizontal overflow '+label);if(errors.length)throw Error(errors.join('\n'))}
const focused=page=>page.evaluate(()=>document.activeElement?.id||document.activeElement?.textContent);
try{
 // 1280 px : demande de modification, motif obligatoire, réponse perdue puis vérification.
 let context=await browser.newContext({viewport:{width:1280,height:900}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/espace');await login(page,email,password);
 let card=page.getByRole('article',{name:'Réponse ordinateur'});await card.waitFor();
 await card.getByRole('button',{name:'Demander une modification…'}).click();if(await focused(page)!=='decision-reason')throw Error('Focus motif');
 await card.getByRole('button',{name:'Envoyer la demande'}).click();await card.getByText('Indiquez le motif de votre demande de modification.').waitFor();if(await focused(page)!=='decision-reason')throw Error('Focus erreur motif');
 await card.getByLabel('Motif de la modification (obligatoire)').fill('Merci d’ajouter une animation musicale en soirée.');
 await page.evaluate(()=>{const original=window.fetch.bind(window);let once=false;window.fetch=async(...args)=>{const r=await original(...args);if(!once&&String(args[0]).includes('/api/me/quote-decision')&&r.ok){once=true;throw new TypeError('Lost confirmation')}return r}});
 await card.getByRole('button',{name:'Envoyer la demande'}).click();await page.getByRole('button',{name:'Vérifier ma réponse'}).click();
 await page.getByText('Demande de modification enregistrée.',{exact:false}).waitFor();
 card=page.getByRole('article',{name:'Réponse ordinateur'});const text=await card.textContent();
 if(!text.includes('Modification demandée')||!text.includes('animation musicale')||await card.getByRole('button',{name:'Accepter le devis…'}).count())throw Error('Résultat modification: '+text);
 await page.screenshot({path:'tmp/quote-decisions/client-1280.png',fullPage:true});await check(page,errors,'client 1280');await context.close();
 // Chloé : réponse visible, contre-proposition, suivi des devis.
 context=await browser.newContext({viewport:{width:1280,height:900}});page=await context.newPage();errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/evenements/'+ids[0]+'/devis');await login(page,'chloe@example.invalid',fs.readFileSync('.secrets/demo-password.txt','utf8').trim());
 await page.getByRole('heading',{name:'Réponses du client'}).waitFor();if(!(await page.locator('.decisions').textContent()).includes('animation musicale'))throw Error('Motif admin');
 await page.getByRole('button',{name:'Préparer une contre-proposition (version 2)'}).click();await page.getByText('Nouvelle version créée',{exact:false}).waitFor();
 if(await page.getByRole('button',{name:'Calculer et enregistrer'}).isDisabled())throw Error('Contre-proposition non modifiable');
 if(!(await page.locator('p',{hasText:'Statut du devis'}).textContent()).includes('version 2'))throw Error('Version 2 non affichée');
 await page.screenshot({path:'tmp/quote-decisions/admin-1280.png',fullPage:true});
 await page.goto('http://127.0.0.1:5173/devis');await page.getByRole('heading',{name:'Suivi des devis'}).waitFor();await page.getByLabel('Filtrer par statut').selectOption('modification');await page.waitForLoadState('networkidle');
 await page.screenshot({path:'tmp/quote-decisions/suivi-1280.png',fullPage:true});await check(page,errors,'admin 1280');await context.close();
 // 390 px : annulation (retour du focus) puis acceptation.
 context=await browser.newContext({viewport:{width:390,height:900}});page=await context.newPage();errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/espace');await login(page,email,password);
 card=page.getByRole('article',{name:'Réponse téléphone'});await card.waitFor();
 await card.getByRole('button',{name:'Accepter le devis…'}).click();await card.getByRole('group',{name:'Accepter ce devis'}).waitFor();
 await card.getByRole('button',{name:'Annuler'}).click();if(!(await focused(page)).includes('Accepter le devis'))throw Error('Focus après annulation');
 await card.getByRole('button',{name:'Accepter le devis…'}).click();await card.getByRole('button',{name:'Confirmer l’acceptation'}).click();
 await page.getByText('Devis accepté.',{exact:false}).waitFor();if(await focused(page)!=='quotes-result')throw Error('Focus résultat');
 if(!(await page.getByRole('article',{name:'Réponse téléphone'}).textContent()).includes('Statut : Accepté'))throw Error('Statut accepté');
 await page.screenshot({path:'tmp/quote-decisions/client-390.png',fullPage:true});await check(page,errors,'client 390');await context.close();
 console.log('Décisions : modification avec reprise, contre-proposition, suivi et acceptation validés à 1280/390 px.')
}finally{await browser.close()}})().catch(e=>{console.error(e);process.exit(1)});
