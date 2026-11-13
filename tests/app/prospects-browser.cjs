const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright'),fs=require('fs');
(async()=>{
 const ids=(process.env.PROSPECT_TEST_IDS||'').split(',');if(ids.length!==2||!ids.every(x=>/^\d+$/.test(x)))throw Error('Fixtures requises');
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});const results={};
 try{for(const [index,width] of [1280,390].entries()){
  const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage(),errors=[];
  page.on('pageerror',e=>errors.push(e.message));
  await page.goto('http://127.0.0.1:5173/prospects');await page.getByRole('heading',{name:'Connexion',exact:true}).waitFor();
  await page.getByLabel('Adresse électronique').fill('chloe@example.invalid');await page.getByLabel('Mot de passe',{exact:true}).fill(fs.readFileSync('.secrets/demo-password.txt','utf8').trim());
  await page.getByRole('button',{name:'Se connecter',exact:true}).click();await page.waitForURL('**/prospects');
  await page.getByRole('heading',{name:'Demandes reçues',exact:true}).waitFor();
  await page.getByLabel('Entreprise, nom ou adresse électronique').fill(process.env.PROSPECT_TEST_EMAIL);await page.getByRole('button',{name:'Rechercher',exact:true}).click();
  await page.locator('a[href="/prospects/'+ids[index]+'"]').waitFor();
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Débordement liste');
  fs.mkdirSync('tmp/prospects-browser',{recursive:true});await page.screenshot({path:`tmp/prospects-browser/list-${width}.png`,fullPage:true});
  await page.locator('a[href="/prospects/'+ids[index]+'"]').click();await page.getByLabel('Message au prospect').waitFor();
  await page.getByLabel('Message au prospect').fill('Notre équipe ne peut pas prendre en charge ce projet fictif. <b>Texte de test</b>');
  if(!await page.getByRole('button',{name:'Classer en échoué et notifier'}).isDisabled())throw Error('Confirmation absente');
  await page.getByRole('checkbox').check();
  let once=false;await page.route('**/api/admin/prospect-failure',async route=>{if(!once){once=true;const r=await route.fetch();if(r.status()!==200)throw Error('Qualification refusée');await route.abort('failed')}else await route.continue()});
  await page.getByRole('button',{name:'Classer en échoué et notifier'}).click();await page.getByRole('alert').filter({hasText:'pas pu confirmer'}).waitFor();
  await page.getByRole('button',{name:'Vérifier et réessayer'}).click();await page.getByRole('status').filter({hasText:'Demande classée en échoué'}).waitFor();
  if(await page.locator('.message b').count())throw Error('HTML interprété');
  if(await page.evaluate(()=>document.activeElement.id)!=='qualification-result')throw Error('Focus résultat absent');
  if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Débordement fiche');
  await page.screenshot({path:`tmp/prospects-browser/detail-${width}.png`,fullPage:true});if(errors.length)throw Error(errors.join('\n'));
  results[width]={returnAfterLogin:true,search:true,detail:true,confirmation:true,lostResponseRetried:true,plainText:true,resultFocus:true,noOverflow:true,noPageErrors:true};await context.close();
 }
 fs.writeFileSync('tmp/prospects-browser/results.json',JSON.stringify(results,null,2));console.log('Liste et qualification vérifiées à 1280 et 390 px.');
 }finally{await browser.close()}
})().catch(e=>{console.error(e);process.exit(1)});
