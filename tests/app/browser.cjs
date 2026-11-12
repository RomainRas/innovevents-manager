// Nécessite Playwright installé sur le poste (PLAYWRIGHT_MODULE accepte son chemin).
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const fs = require('fs');
(async () => {
  const browser = await chromium.launch({headless:true,
    ...(process.env.CHROMIUM_PATH ? {executablePath:process.env.CHROMIUM_PATH} : {})});
  const errors=[];
  try {
    const page=await browser.newPage();
    page.on('pageerror', e=>errors.push(e.message));
    for (const [label,width,height] of [['desktop',1280,800],['mobile',390,844]]) {
      await page.setViewportSize({width,height});
      await page.goto('http://127.0.0.1:5173/');
      await page.getByRole('heading',{name:'Votre espace se prépare'}).waitFor();
      await page.getByText('La connexion est disponible.',{exact:true}).waitFor();
      await page.getByRole('button',{name:'Vérifier la connexion'}).click();
      await page.getByText('La connexion est disponible.',{exact:true}).waitFor();
      if (await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)) throw new Error('Débordement '+label);
      fs.mkdirSync('tmp/app-browser',{recursive:true});
      await page.screenshot({path:'tmp/app-browser/'+label+'.png',fullPage:true});
    }
    if(errors.length) throw new Error(errors.join('\n'));
    fs.writeFileSync('tmp/app-browser/result.json',JSON.stringify({desktop:true,mobile:true,retry:true,noPageErrors:true},null,2));
    console.log('Navigateur : affichage, appel API et bouton validés en 1280 px et 390 px.');
  } finally { await browser.close(); }
})().catch(e=>{console.error(e);process.exit(1)});
