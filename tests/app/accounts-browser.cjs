// Test dans un vrai navigateur (Chromium piloté par Playwright) : inscription, mot de passe oublié et changement de mot de passe, avec messages d'erreur, sur ordinateur (1280 px) et téléphone (390 px).
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright')
const fs = require('fs')
;(async () => {
  // Navigateur sans fenêtre ; CHROMIUM_PATH permet d'utiliser un Chromium déjà installé.
  const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}),
  })
  const results = {}
  try {
    for (const width of [1280, 390]) {
      const context = await browser.newContext({ viewport: { width, height: 900 } }),
        page = await context.newPage()
      const errors = []
      page.on('pageerror', e => errors.push(e.message))
      await page.goto('http://127.0.0.1:5173/connexion')
      await page.getByRole('link', { name: 'Créer un compte' }).click()
      await page.getByRole('heading', { name: 'Créer votre compte' }).waitFor()
      if ((await page.getByLabel('Confirmer le mot de passe').count()) !== 1) throw Error('Formulaire absent')
      if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw Error('Débordement')
      fs.mkdirSync('tmp/accounts-browser', { recursive: true })
      await page.screenshot({ path: `tmp/accounts-browser/register-${width}.png`, fullPage: true })
      await page.getByRole('link', { name: 'Retour à la connexion' }).click()
      await page.getByRole('link', { name: 'Mot de passe oublié ?' }).click()
      await page.getByRole('heading', { name: 'Mot de passe oublié', exact: true }).waitFor()
      await page.getByLabel('Adresse électronique').fill('browser-' + width + '@example.invalid')
      const responsePromise = page.waitForResponse(r => r.url().endsWith('/api/auth/forgot'))
      await page.getByRole('button', { name: 'Recevoir un mot de passe temporaire' }).click()
      const response = await responsePromise
      if (response.status() !== 202)
        throw Error('Récupération : HTTP ' + response.status() + ' ' + (await response.text()))
      await page.getByRole('status').filter({ hasText: 'Si un compte actif' }).waitFor()
      await page.getByRole('link', { name: 'Retour à la connexion' }).click()
      await page.getByRole('heading', { name: 'Connexion', exact: true }).waitFor()
      await page.getByLabel('Adresse électronique').fill('alice@example.invalid')
      await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(fs.readFileSync('.secrets/demo-password.txt', 'utf8').trim())
      await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
      await page.waitForURL('**/espace')
      await page.getByRole('link', { name: 'Changer mon mot de passe' }).click()
      await page.getByLabel('Mot de passe actuel ou temporaire').waitFor()
      if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw Error('Débordement')
      await page.screenshot({ path: `tmp/accounts-browser/change-${width}.png`, fullPage: true })
      if (errors.length) throw Error(errors.join('\n'))
      results[width] = {
        registrationForm: true,
        forgotGeneric: true,
        changeForm: true,
        noOverflow: true,
        noPageErrors: true,
      }
      await context.close()
    }
    fs.writeFileSync('tmp/accounts-browser/results.json', JSON.stringify(results, null, 2))
    console.log('Formulaires vérifiés à 1280 et 390 px.')
  } finally {
    // Le navigateur est toujours fermé, même en cas d'échec.
    await browser.close()
  }
})().catch(e => {
  console.error(e)
  process.exit(1)
})
