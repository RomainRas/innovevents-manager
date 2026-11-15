// Test dans un vrai navigateur (Chromium piloté par Playwright) : image d'un événement : ajout, reprise après réponse perdue, remplacement et conservation, à 1280 et 390 px.
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright'),
  fs = require('fs')
;(async () => {
  const ids = (process.env.EVENT_IMAGE_IDS || '').split(',')
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
      await page.goto('http://127.0.0.1:5173/evenements/' + ids[i])
      await page.getByRole('heading', { name: 'Connexion', exact: true }).waitFor()
      await page.getByLabel('Adresse électronique').fill('chloe@example.invalid')
      await page
        .getByLabel('Mot de passe', { exact: true })
        .fill(fs.readFileSync('.secrets/demo-password.txt', 'utf8').trim())
      await page.getByRole('button', { name: 'Se connecter', exact: true }).click()
      await page.getByRole('heading', { name: 'Image de l’événement', exact: true }).waitFor()
      await page.getByRole('button', { name: 'Enregistrer l’image', exact: true }).click()
      await page.getByRole('alert').waitFor()
      if ((await page.evaluate(() => document.activeElement.id)) !== 'image-alternative')
        throw Error('Focus description')
      await page.getByLabel('Description de l’image').fill('Salle de séminaire')
      await page.getByLabel('Choisir une image').setInputFiles('tmp/event-image-fixture.png')
      // Simulate a lost confirmation after a real multipart transfer. Replaying a
      // Chromium intercepted multipart body can omit its file bytes.
      await page.evaluate(() => {
        const original = window.fetch.bind(window)
        let once = false
        window.fetch = async (...args) => {
          const response = await original(...args)
          if (!once && String(args[0]).includes('/api/admin/event-image-upload') && response.ok) {
            once = true
            throw new TypeError('Simulated lost response')
          }
          return response
        }
      })
      await page.getByRole('button', { name: 'Enregistrer l’image', exact: true }).click()
      await page.getByRole('button', { name: 'Vérifier et réessayer l’image' }).waitFor()
      if (!(await page.getByRole('button', { name: 'Enregistrer le brouillon', exact: true }).isDisabled()))
        throw Error('Formulaire concurrent non bloqué')
      await page.getByRole('button', { name: 'Vérifier et réessayer l’image' }).click()
      await page.getByText('Image enregistrée.', { exact: true }).waitFor()
      if ((await page.evaluate(() => document.activeElement.id)) !== 'image-result') throw Error('Focus résultat')
      await page.getByRole('img', { name: 'Salle de séminaire', exact: true }).waitFor()
      await page.waitForFunction(() => document.querySelector('.event-picture')?.naturalWidth === 120)
      await page.getByLabel('Remplacer l’image').setInputFiles('tmp/event-image-fixture.jpg')
      await page.getByLabel('Description de l’image').fill('Nouvelle salle')
      await page.getByRole('button', { name: 'Enregistrer l’image', exact: true }).click()
      await page.getByRole('img', { name: 'Nouvelle salle', exact: true }).waitFor()
      await page.reload()
      await page.getByRole('img', { name: 'Nouvelle salle', exact: true }).waitFor()
      await page.waitForFunction(() => document.querySelector('.event-picture')?.naturalWidth === 120)
      await page.getByLabel('Nom de l’événement').fill('Image et brouillon modifiés')
      await page.getByRole('button', { name: 'Enregistrer le brouillon', exact: true }).click()
      await page.getByText('Brouillon enregistré.', { exact: true }).waitFor()
      if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)) throw Error('Débordement')
      if (errors.length) throw Error(errors.join('\n'))
      fs.mkdirSync('tmp/event-images-browser', { recursive: true })
      await page.screenshot({ path: `tmp/event-images-browser/image-${width}.png`, fullPage: true })
      await context.close()
    }
    console.log('Images : ajout, reprise, remplacement et persistance vérifiés à 1280/390 px.')
  } finally {
    // Le navigateur est toujours fermé, même en cas d'échec.
    await browser.close()
  }
})().catch(e => {
  console.error(e)
  process.exit(1)
})
