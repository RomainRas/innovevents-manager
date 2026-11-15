// Test dans un vrai navigateur (Chromium piloté par Playwright) : préparation d'un devis (prestations, TVA, totaux), à 1280 et 390 px.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright'),
  fs = require('fs')
;(async () => {
  const ids = (process.env.QUOTE_TEST_IDS || '').split(',')
  if (ids.length !== 2 || !ids.every(x => /^\d+$/.test(x))) throw Error('Fixtures')
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
      await page.goto('http://127.0.0.1:5173/evenements/' + ids[i] + '/devis')
      await page.getByLabel('Adresse électronique').fill('chloe@example.invalid')
      await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(fs.readFileSync('.secrets/demo-password.txt', 'utf8').trim())
      await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
      await page.getByRole('heading', { name: 'Préparer le devis', exact: true }).waitFor()
      await page.getByRole('button', { name: 'Calculer et enregistrer' }).click()
      await page.getByRole('alert').waitFor()
      if ((await page.evaluate(() => document.activeElement.id)) !== 'taux_tva') throw Error('Focus TVA')
      await page.getByLabel('Prestation 1 — libellé').fill('Location de salle')
      await page.getByLabel('Montant HT 1 (€)').fill('1000')
      await page.getByLabel('Taux de TVA (%)').fill('20')
      await page.getByRole('button', { name: 'Ajouter une prestation' }).click()
      await page.getByLabel('Prestation 2 — libellé').fill('Traiteur')
      await page.getByLabel('Montant HT 2 (€)').fill('250,50')
      await page.evaluate(() => {
        const original = window.fetch.bind(window)
        let once = false
        window.fetch = async (...args) => {
          const r = await original(...args)
          if (!once && String(args[0]).includes('/api/admin/quote-draft-save') && r.ok) {
            once = true
            throw new TypeError('Lost response')
          }
          return r
        }
      })
      await page.getByRole('button', { name: 'Calculer et enregistrer' }).click()
      await page.getByRole('button', { name: 'Vérifier et réessayer' }).click()
      await page.getByText('Prestations et totaux enregistrés.', { exact: true }).waitFor()
      await page.getByText('1500.60 €', { exact: true }).waitFor()
      if ((await page.evaluate(() => document.activeElement.id)) !== 'quote-result') throw Error('Focus résultat')
      await page.getByRole('button', { name: 'Supprimer la prestation 2' }).click()
      if (await page.getByRole('heading', { name: 'Totaux enregistrés' }).count())
        throw Error('Totaux périmés affichés')
      await page.getByRole('button', { name: 'Calculer et enregistrer' }).click()
      await page.getByText('1200.00 €', { exact: true }).waitFor()
      await page.reload()
      await page.getByText('1200.00 €', { exact: true }).waitFor()
      if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw Error('Débordement')
      if (errors.length) throw Error(errors.join('\n'))
      fs.mkdirSync('tmp/quote-drafts-browser', { recursive: true })
      await page.screenshot({ path: `tmp/quote-drafts-browser/devis-${width}.png`, fullPage: true })
      await context.close()
    }
    console.log('Devis : parcours 1280/390 px validé.')
  } finally {
    // Le navigateur est toujours fermé, même en cas d'échec.
    await browser.close()
  }
})().catch(e => {
  console.error(e)
  process.exit(1)
})
