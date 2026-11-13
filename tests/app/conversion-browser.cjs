const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),fs=require('fs');
(async()=>{
 const ids=(process.env.CONVERSION_TEST_IDS||'').split(',');if(ids.length!==2||!ids.every(x=>/^\d+$/.test(x)))throw Error('Fixtures requises');
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});const results={};
 try{for(const [index,width] of [1280,390].entries()){
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:5173/prospects/'+ids[index]+'/convertir');await page.getByRole('heading',{name:'Connexion',exact:true}).waitFor();
  await page.getByLabel('Adresse électronique').fill('chloe@example.invalid');await page.getByLabel('Mot de passe',{exact:true}).fill(fs.readFileSync('.secrets/demo-password.txt','utf8').trim());
  await page.getByRole('button',{name:'Se connecter',exact:true}).click();await page.waitForURL('**/convertir');await page.getByLabel('Entreprise',{exact:true}).waitFor();
  if(await page.getByLabel('Adresse électronique').inputValue()!==process.env.CONVERSION_TEST_EMAIL)throw Error('Coordonnées non reprises');
  if(await page.getByLabel('Prénom du contact').inputValue()!=='Prénom')throw Error('Prénom non repris');
  if(!await page.getByRole('button',{name:'Créer la fiche client'}).isDisabled())throw Error('Confirmation absente');
  await page.getByLabel('Nom du contact',{exact:true}).fill('');await page.getByRole('checkbox').check();await page.getByRole('button',{name:'Créer la fiche client'}).click();
  await page.getByRole('alert').filter({hasText:'Vérifiez les champs'}).waitFor();
  if(await page.evaluate(()=>document.activeElement.id)!=='contact_nom')throw Error('Focus erreur absent');
  await page.getByLabel('Nom du contact',{exact:true}).fill('Contact corrigé');await page.getByLabel('Entreprise',{exact:true}).fill('Client navigateur '+width);
  let once=false;await page.route('**/api/admin/prospect-conversion',async route=>{if(!once){once=true;const r=await route.fetch();if(r.status()!==200)throw Error('Conversion refusée');await route.abort('failed')}else await route.continue()});
  await page.getByRole('button',{name:'Créer la fiche client'}).click();await page.getByRole('alert').filter({hasText:'pas pu confirmer'}).waitFor();
  await page.getByRole('button',{name:'Vérifier et réessayer'}).click();await page.getByRole('heading',{name:'La fiche client est créée'}).waitFor();
  if(await page.evaluate(()=>document.activeElement.id)!=='conversion-success')throw Error('Focus résultat absent');
  await page.getByRole('link',{name:'Consulter la fiche client'}).click();await page.getByRole('heading',{name:'Client navigateur '+width,exact:true}).waitFor();
  await page.getByText('Contact corrigé',{exact:true}).waitFor();
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Débordement');
  fs.mkdirSync('tmp/conversion-browser',{recursive:true});await page.screenshot({path:`tmp/conversion-browser/client-${width}.png`,fullPage:true});if(errors.length)throw Error(errors.join('\n'));
  results[width]={returnAfterLogin:true,prefilled:true,confirmation:true,errorFocus:true,editedFields:true,lostResponseRetried:true,resultFocus:true,clientDetail:true,noOverflow:true,noPageErrors:true};await context.close();
 }fs.writeFileSync('tmp/conversion-browser/results.json',JSON.stringify(results,null,2));console.log('Conversion vérifiée à 1280 et 390 px.');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
