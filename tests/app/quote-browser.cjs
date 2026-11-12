const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const fs=require('fs'),crypto=require('crypto'),{spawnSync}=require('child_process');
const email='quote-browser-'+crypto.randomBytes(8).toString('hex')+'@example.invalid',keys=new Set();
function sql(query){const r=spawnSync(process.env.DOCKER_BIN||'docker',['compose','exec','-T','mysql','sh','/opt/innovevents/client.sh','--batch','--skip-column-names','innovevents_demo'],{input:query,encoding:'utf8'});if(r.status!==0)throw Error('Nettoyage SQL échoué');}
(async()=>{
 const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});const results={};
 try {
  for(const width of [1280,390]){
   const context=await browser.newContext({viewport:{width,height:900}}),page=await context.newPage();const errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   await page.goto('http://127.0.0.1:5173/demande-devis');
   await page.getByRole('heading',{name:'Parlons de votre événement'}).waitFor();
   await page.getByRole('button',{name:'Envoyer ma demande'}).click();
   await page.getByRole('alert').filter({hasText:'Vérifiez les champs'}).waitFor();
   if(await page.locator('[aria-invalid="true"]').count()!==10)throw Error('Dix erreurs attendues');
   if(await page.evaluate(()=>document.activeElement.id)!=='entreprise')throw Error('Focus première erreur absent');
   await page.keyboard.press('Tab');
   if(await page.evaluate(()=>document.activeElement.id)!=='nom')throw Error('Ordre clavier incorrect');
   const values={entreprise:'Société test navigateur',nom:'Test',prenom:'Visiteur',email,telephone:'0600000000',lieu:'Lyon',type_evenement:'Séminaire',date_souhaitee:'2031-06-10',participants_estimes:'30',besoin:'Demande fictive de vérification sur navigateur.'};
   for(const [id,value] of Object.entries(values))await page.locator('#'+id).fill(value);
   fs.mkdirSync('tmp/quote-browser',{recursive:true});
   if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Débordement');
   await page.screenshot({path:`tmp/quote-browser/form-${width}.png`,fullPage:true});
   let intercepted=false;
   await page.route('**/api/quote-requests',async route=>{
    const key=JSON.parse(route.request().postData()).submission_key;
    if(!/^[a-f0-9]{64}$/.test(key))throw Error('Clé invalide');keys.add(key);
    if(!intercepted){intercepted=true;const response=await route.fetch();if(response.status()!==200)throw Error('Enregistrement refusé');await route.abort('failed');}
    else {const response=await route.fetch();const body=await response.json();await route.fulfill({response,json:{...body,message:'Merci <img src=x onerror="window.quoteXss=true">'}});}
   });
   await page.getByRole('button',{name:'Envoyer ma demande'}).click();
   await page.getByRole('alert').filter({hasText:'pas pu confirmer'}).waitFor();
   if(await page.locator('#entreprise').inputValue()!==values.entreprise)throw Error('Saisie perdue');
   if(!await page.locator('#entreprise').isDisabled())throw Error('Modification possible pendant résultat incertain');
   await page.getByRole('button',{name:'Vérifier et réessayer'}).click();
   await page.getByRole('heading',{name:'Votre demande est enregistrée'}).waitFor();
   if(await page.locator('#quote-success img').count()||await page.evaluate(()=>!!window.quoteXss))throw Error('HTML exécuté');
   if(await page.evaluate(()=>document.activeElement.id)!=='quote-success')throw Error('Focus confirmation absent');
   await page.screenshot({path:`tmp/quote-browser/success-${width}.png`,fullPage:true});
   if(errors.length)throw Error(errors.join('\n'));
   results[width]={tenErrors:true,errorFocus:true,keyboardTab:true,valuesPreserved:true,lostResponseRetried:true,plainTextConfirmation:true,successFocus:true,noOverflow:true,noPageErrors:true};
   await context.close();
  }
  fs.writeFileSync('tmp/quote-browser/results.json',JSON.stringify(results,null,2));console.log('Formulaire, erreurs, réponse perdue et reprise vérifiés en 1280/390 px.');
 } finally {
  await browser.close();
  if(keys.size)sql("DELETE FROM soumissions_devis WHERE cle IN ("+[...keys].map(x=>"'"+x+"'").join(',')+");");
  sql("DELETE FROM prospects WHERE email='"+email+"';");
 }
})().catch(e=>{console.error(e);process.exit(1)});
