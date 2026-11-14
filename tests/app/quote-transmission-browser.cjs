const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),fs=require('fs');
// Parcours navigateur : transmission (plateforme à 1280 px, manuelle à 390 px) puis consultation par le client.
(async()=>{const ids=(process.env.SEND_TEST_IDS||'').split(','),email=process.env.SEND_TEST_EMAIL,password=process.env.SEND_TEST_PASSWORD;
if(ids.length!==2||!ids.every(x=>/^\d+$/.test(x))||!email||!password)throw Error('Fixtures');
const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});fs.mkdirSync('tmp/quote-transmission',{recursive:true});
async function login(page,address,secret){await page.getByLabel('Adresse électronique').fill(address);await page.getByLabel('Mot de passe',{exact:true}).fill(secret);await page.getByRole('button',{name:'Se connecter',exact:true}).click()}
async function check(page,errors,label){if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Horizontal overflow '+label);if(errors.length)throw Error(errors.join('\n'))}
try{for(const [i,width] of [1280,390].entries()){
 const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto('http://127.0.0.1:5173/evenements/'+ids[i]+'/devis');await login(page,'chloe@example.invalid',fs.readFileSync('.secrets/demo-password.txt','utf8').trim());
 await page.getByRole('heading',{name:'Transmission au client',exact:true}).waitFor();
 if(i===0){
  await page.getByRole('button',{name:'Envoyer par la plateforme…'}).click();const dialog=page.getByRole('group',{name:'Confirmer l’envoi par la plateforme'});await dialog.waitFor();
  if(!(await dialog.textContent()).includes(email))throw Error('Recipient not shown');if(await page.evaluate(()=>document.activeElement.id)!=='send-confirm')throw Error('Focus confirmation');
  // Réponse perdue après enregistrement : l'interface doit proposer une vérification sans doublon.
  await page.evaluate(()=>{const original=window.fetch.bind(window);let once=false;window.fetch=async(...args)=>{const r=await original(...args);if(!once&&String(args[0]).includes('/api/admin/quote-send')&&r.ok){once=true;throw new TypeError('Lost confirmation')}return r}});
  await page.getByRole('button',{name:'Confirmer',exact:true}).click();await page.getByRole('button',{name:'Vérifier le résultat'}).waitFor();
  if(!await page.getByRole('button',{name:'Calculer et enregistrer'}).isDisabled())throw Error('Form not locked while uncertain');
  await page.getByRole('button',{name:'Vérifier le résultat'}).click();await page.getByText('Envoi demandé : le courriel avec le PDF est en file d’envoi.',{exact:false}).waitFor();
 }else{
  await page.getByRole('button',{name:'Déclarer un envoi manuel…'}).click();await page.getByRole('button',{name:'Confirmer',exact:true}).click();
  await page.getByText('Cochez la déclaration pour confirmer l’envoi manuel.').waitFor();if(await page.evaluate(()=>document.activeElement.id)!=='send-declared')throw Error('Focus declaration');
  await page.getByLabel('J’ai envoyé ce PDF au client depuis ma messagerie.').check();await page.getByRole('button',{name:'Confirmer',exact:true}).click();await page.getByText('Envoi manuel enregistré.',{exact:false}).waitFor();
 }
 if(!(await page.locator('p',{hasText:'Statut du devis'}).textContent()).includes('Étude côté client'))throw Error('Status not updated');
 if(await page.getByRole('listitem').filter({hasText:email}).count()!==1)throw Error('History');
 if(!await page.getByRole('button',{name:'Calculer et enregistrer'}).isDisabled())throw Error('Frozen quote still editable');
 await page.screenshot({path:`tmp/quote-transmission/admin-${width}.png`,fullPage:true});await check(page,errors,'admin '+width);await context.close();
 const client=await browser.newContext({viewport:{width,height:900}}),cp=await client.newPage(),cerrors=[];cp.on('pageerror',e=>cerrors.push(e.message));
 await cp.goto('http://127.0.0.1:5173/espace');await login(cp,email,password);await cp.getByRole('heading',{name:'Vos devis',exact:true}).waitFor();
 const card=cp.getByRole('article',{name:i===0?'Envoi ordinateur':'Envoi téléphone'});await card.waitFor();
 const text=(await card.textContent()).replace(/\s/g,' ');if(!text.includes('À étudier')||!text.includes('1 800,00'))throw Error('Client quote content');
 const download=cp.waitForEvent('download');await card.getByRole('button',{name:'Télécharger le PDF'}).click();const file=await download;await file.saveAs(`tmp/quote-transmission/client-${width}.pdf`);
 if(!fs.readFileSync(`tmp/quote-transmission/client-${width}.pdf`).subarray(0,5).equals(Buffer.from('%PDF-')))throw Error('Invalid client download');
 await cp.screenshot({path:`tmp/quote-transmission/client-${width}.png`,fullPage:true});await check(cp,cerrors,'client '+width);await client.close();
}console.log('Transmission : envoi plateforme avec reprise, envoi manuel et espace client validés à 1280/390 px.')}finally{await browser.close()}})().catch(e=>{console.error(e);process.exit(1)});
