// Test de base dans un vrai navigateur : page d'accueil affichée, appel à l'API et bouton fonctionnel, à 1280 et 390 px.
// Nécessite Playwright installé sur le poste (PLAYWRIGHT_MODULE accepte son chemin).
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright')
const fs = require('fs')
;(async () => {
  // Navigateur sans fenêtre ; CHROMIUM_PATH permet d'utiliser un Chromium déjà installé.
  const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}),
  })
  const errors = []
  try {
    const page = await browser.newPage()
    page.on('pageerror', e => errors.push(e.message))
    // Même parcours sur ordinateur et sur téléphone.
    for (const [label, width, height] of [
      ['desktop', 1280, 800],
      ['mobile', 390, 844],
    ]) {
      await page.setViewportSize({ width, height })
      await page.goto('http://127.0.0.1:5173/')
      await page.getByRole('heading', { name: 'Votre espace se prépare' }).waitFor()
      await page.getByText('La connexion est disponible.', { exact: true }).waitFor()
      await page.getByRole('button', { name: 'Vérifier la connexion' }).click()
      await page.getByText('La connexion est disponible.', { exact: true }).waitFor()
      if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth))
        throw new Error('Débordement ' + label)
      fs.mkdirSync('tmp/app-browser', { recursive: true })
      await page.screenshot({ path: 'tmp/app-browser/' + label + '.png', fullPage: true })
    }
    if (errors.length) throw new Error(errors.join('\n'))
    fs.writeFileSync(
      'tmp/app-browser/result.json',
      JSON.stringify({ desktop: true, mobile: true, retry: true, noPageErrors: true }, null, 2),
    )
    console.log('Navigateur : affichage, appel API et bouton validés en 1280 px et 390 px.')
  } finally {
    // Le navigateur est toujours fermé, même en cas d'échec.
    await browser.close()
  }
})().catch(e => {
  console.error(e)
  process.exit(1)
})
