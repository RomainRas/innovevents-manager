const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const fs=require('fs');
(async()=>{
  const browser=await chromium.launch({headless:true,...(process.env.CHROMIUM_PATH?{executablePath:process.env.CHROMIUM_PATH}:{})});
  const password=fs.readFileSync('.secrets/demo-password.txt','utf8').trim();
  const results={};
  try {
    for (const [label,width,height] of [['desktop',1280,900],['mobile',390,844]]) {
      const context=await browser.newContext({viewport:{width,height}}),page=await context.newPage();
      const errors=[];page.on('pageerror',e=>errors.push(e.message));
      await page.goto('http://127.0.0.1:5173/espace');
      await page.waitForURL('**/connexion?retour=/espace');
      await page.getByRole('heading',{name:'Connexion',exact:true}).waitFor();
      await page.getByLabel('Adresse électronique').fill('alice@example.invalid');
      await page.getByLabel('Mot de passe',{exact:true}).fill('incorrect');
      await page.getByRole('button',{name:'Se connecter',exact:true}).click();
      await page.getByRole('alert').filter({hasText:'Adresse électronique ou mot de passe incorrect.'}).waitFor();
      if(await page.getByLabel('Mot de passe',{exact:true}).inputValue()!=='')throw Error('Mot de passe conservé');
      fs.mkdirSync('tmp/auth-browser',{recursive:true});
      await page.screenshot({path:'tmp/auth-browser/login-'+label+'.png',fullPage:true});
      await page.getByLabel('Mot de passe',{exact:true}).fill(password);
      await page.getByRole('button',{name:'Se connecter',exact:true}).click();
      await page.waitForURL('**/espace');await page.getByRole('heading',{name:'Bonjour Alice'}).waitFor();
      await page.getByText('Séminaire Horizon — démonstration',{exact:false}).waitFor();
      await page.reload();await page.getByRole('heading',{name:'Bonjour Alice'}).waitFor();
      if(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth))throw Error('Débordement');
      await page.screenshot({path:'tmp/auth-browser/account-'+label+'.png',fullPage:true});
      await page.getByRole('button',{name:'Se déconnecter'}).click();await page.waitForURL('**/connexion');
      if(errors.length)throw Error(errors.join('\n'));
      results[label]={redirect:true,wrongPassword:true,passwordCleared:true,login:true,reloadSession:true,logout:true,noOverflow:true,noPageErrors:true};
      await context.close();
    }
    fs.writeFileSync('tmp/auth-browser/results.json',JSON.stringify(results,null,2));
    console.log('Connexion, retour à la page demandée, rechargement et déconnexion vérifiés sur ordinateur et mobile.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e);process.exit(1)});
