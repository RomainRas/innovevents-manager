// Test dans un vrai navigateur (Chromium piloté par Playwright) : brouillon d'événement créé depuis une demande convertie puis modifié, à 1280 et 390 px.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright'),
  fs = require('fs')
;(async () => {
  const ids = (process.env.EVENT_TEST_IDS || '').split(',')
  if (ids.length !== 2 || !ids.every(x => /^\d+$/.test(x))) throw Error('Fixtures requises')
  // Navigateur sans fenêtre ; CHROMIUM_PATH permet d'utiliser un Chromium déjà installé.
  const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}),
  })
  try {
    for (const [i, width] of [1280, 390].entries()) {
      const context = await browser.newContext({ viewport: { width, height: 900 } }),
        page = await context.newPage(),
        errors = []
      page.on('pageerror', e => errors.push(e.message))
      await page.goto('http://127.0.0.1:5173/prospects/' + ids[i] + '/evenement')
      await page.getByRole('heading', { name: 'Connexion', exact: true }).waitFor()
      await page.getByLabel('Adresse électronique').fill('chloe@example.invalid')
      await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(fs.readFileSync('.secrets/demo-password.txt', 'utf8').trim())
      await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
      await page.getByRole('heading', { name: 'Préparer l’événement', exact: true }).waitFor()
      await page.getByLabel('Nom de l’événement').waitFor()
      if ((await page.getByLabel('Nom de l’événement').inputValue()) !== 'Séminaire') throw Error('Préremplissage')
      await page.getByText('Les heures restent à préciser.', { exact: false }).waitFor()
      await page.getByLabel('Nom de l’événement').fill('')
      await page.getByRole('button', { name: 'Créer le brouillon' }).click()
      await page.getByRole('alert').waitFor()
      if ((await page.evaluate(() => document.activeElement.id)) !== 'nom') throw Error('Focus erreur')
      await page.getByLabel('Nom de l’événement').fill('Séminaire navigateur ' + width)
      let once = false
      await page.route('**/api/admin/event-create', async r => {
        if (!once) {
          once = true
          const res = await r.fetch()
          if (res.status() !== 200) throw Error('Création refusée')
          await r.abort('failed')
        } else await r.continue()
      })
      await page.getByRole('button', { name: 'Créer le brouillon' }).click()
      await page.getByRole('button', { name: 'Vérifier et réessayer' }).click()
      await page.waitForURL('**/evenements/*?cree=1')
      await page.getByRole('button', { name: 'Enregistrer le brouillon', exact: true }).waitFor()
      await page.getByLabel('Date et heure de début').fill('2031-07-01T10:00')
      await page.getByLabel('Date et heure de fin').fill('2031-07-01T12:00')
      await page.getByRole('button', { name: 'Enregistrer le brouillon', exact: true }).click()
      await page.getByText('Brouillon enregistré.', { exact: true }).waitFor()
      if ((await page.evaluate(() => document.activeElement.id)) !== 'event-result') throw Error('Focus résultat')
      await page.reload()
      await page.getByLabel('Date et heure de début').waitFor()
      if ((await page.getByLabel('Date et heure de début').inputValue()) !== '2031-07-01T10:00')
        throw Error('Persistance')
      if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw Error('Débordement')
      if (errors.length) throw Error(errors.join('\n'))
      fs.mkdirSync('tmp/events-browser', { recursive: true })
      await page.screenshot({ path: `tmp/events-browser/event-${width}.png`, fullPage: true })
      await context.close()
    }
    console.log('Événements : parcours validé à 1280 et 390 px.')
  } finally {
    // Le navigateur est toujours fermé, même en cas d'échec.
    await browser.close()
  }
})().catch(e => {
  console.error(e)
  process.exit(1)
})
