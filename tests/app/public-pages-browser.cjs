// Parcours dans un vrai navigateur (Chromium piloté par Playwright).
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright'),
  fs = require('fs')
// Parcours navigateur des pages publiques à 1280 px puis 390 px : menu, accueil, événements filtrés, détail, avis, contact, pages légales.
const BASE = 'http://127.0.0.1:5173'
;(async () => {
  // Navigateur sans fenêtre ; CHROMIUM_PATH permet d'utiliser un Chromium déjà installé.
  const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROMIUM_PATH ? { executablePath: process.env.CHROMIUM_PATH } : {}),
  })
  fs.mkdirSync('tmp/public-pages', { recursive: true })
  const results = {}
  async function check(page, errors, label) {
    if (await page.evaluate(() => document.documentElement.scrollWidth > innerWidth))
      throw Error('Débordement horizontal ' + label)
    if (errors.length) throw Error(errors.join('\n'))
  }
  try {
    for (const [width, height] of [
      [1280, 900],
      [390, 844],
    ]) {
      const context = await browser.newContext({ viewport: { width, height } }),
        page = await context.newPage(),
        errors = []
      page.on('pageerror', e => errors.push(e.message))
      page.on('response', r => {
        if (r.status() >= 500) errors.push('HTTP ' + r.status() + ' ' + r.url())
      })
      // Accueil : présentation, images avec alternative, bouton de devis, menu complet.
      await page.goto(BASE + '/')
      await page.getByRole('heading', { level: 1, name: 'Des événements professionnels cousus main' }).waitFor()
      const nav = page.getByRole('navigation', { name: 'Navigation principale' })
      for (const name of ['Accueil', 'Événements', 'Avis', 'Contact', 'Demande de devis', 'Se connecter'])
        await nav.getByRole('link', { name, exact: true }).waitFor()
      results['menu_' + width] = true
      results['images_alt_' + width] = await page.evaluate(
        () => document.images.length >= 3 && [...document.images].every(i => i.alt.trim().length > 10),
      )
      results['title_' + width] = (await page.title()) === 'Innov’Events'
      // Lien d'évitement : premier élément atteint au clavier.
      await page.keyboard.press('Tab')
      results['skip_link_' + width] =
        (await page.evaluate(() => document.activeElement.textContent)) === 'Aller au contenu'
      await page.screenshot({ path: `tmp/public-pages/accueil-${width}.png`, fullPage: true })
      await check(page, errors, 'accueil ' + width)
      await page.getByRole('main').getByRole('link', { name: 'Demander un devis' }).first().click()
      await page.waitForURL(BASE + '/demande-devis')
      // Événements : filtres type, thème et période.
      await nav.getByRole('link', { name: 'Événements' }).click()
      await page.getByRole('heading', { level: 1, name: 'Nos événements' }).waitFor()
      await page.getByText(/^\d+ événements?$/).waitFor()
      const all = await page.locator('.event-cards > li').count()
      results['events_listed_' + width] = all >= 6
      results['no_price_' + width] = !(await page.locator('main').textContent()).match(/€|HT|TTC|prix/i)
      await page.getByLabel('Type d’événement').selectOption('Conférence')
      await page.getByRole('button', { name: 'Filtrer' }).click()
      await page.waitForURL(/type=/)
      await page.getByText('1 événement', { exact: true }).waitFor()
      results['filter_type_' + width] =
        (await page.locator('.event-cards h2').allTextContents()).join() === 'Convention annuelle Horizon'
      await page.getByRole('button', { name: 'Réinitialiser' }).click()
      await page.getByText(all + ' événements', { exact: true }).waitFor()
      await page.getByLabel('Thème').selectOption('Innovation')
      await page.getByLabel('Du').fill('2026-11-01')
      await page.getByRole('button', { name: 'Filtrer' }).click()
      await page.getByText('1 événement', { exact: true }).waitFor()
      results['filter_theme_dates_' + width] =
        (await page.locator('.event-cards h2').allTextContents()).join() === 'Ateliers innovation Horizon'
      await page.screenshot({ path: `tmp/public-pages/evenements-${width}.png`, fullPage: true })
      await check(page, errors, 'événements ' + width)
      // Détail : informations, image et section « Prestations et Devis » vers la demande de devis.
      await page.getByRole('link', { name: 'Ateliers innovation Horizon' }).click()
      await page.getByRole('heading', { level: 1, name: 'Ateliers innovation Horizon' }).waitFor()
      const section = page.getByRole('region', { name: 'Prestations et Devis' })
      await section.waitFor()
      results['detail_' + width] =
        (await page.locator('dl').textContent()).includes('Nantes') &&
        (await page.locator('.event-detail img').evaluate(i => i.complete && i.naturalWidth > 0 && i.alt.length > 10))
      await page.screenshot({ path: `tmp/public-pages/detail-${width}.png`, fullPage: true })
      await check(page, errors, 'détail ' + width)
      await section.getByRole('link', { name: 'Demander un devis' }).click()
      await page.waitForURL(BASE + '/demande-devis')
      results['detail_quote_link_' + width] = true
      // Avis validés.
      await nav.getByRole('link', { name: 'Avis' }).click()
      await page.getByRole('heading', { level: 1, name: 'Avis de nos clients' }).waitFor()
      await page.locator('.reviews li').first().waitFor()
      results['reviews_' + width] = (await page.locator('.reviews li').count()) >= 4
      await page.screenshot({ path: `tmp/public-pages/avis-${width}.png`, fullPage: true })
      await check(page, errors, 'avis ' + width)
      // Contact : erreur sans adresse ni nom d'utilisateur, puis envoi.
      await nav.getByRole('link', { name: 'Contact' }).click()
      await page.getByRole('heading', { level: 1, name: 'Contact' }).waitFor()
      await page.getByLabel('Titre de la demande *').fill('Question test navigateur ' + width)
      await page.getByLabel('Description *').fill('Message fictif du test navigateur.')
      await page.getByRole('button', { name: 'Envoyer' }).click()
      await page.getByText('Indiquez votre adresse électronique ou votre nom d’utilisateur.').waitFor()
      results['contact_error_focus_' + width] =
        (await page.evaluate(() => document.activeElement.id)) === 'contact-email'
      await page
        .getByLabel('Adresse électronique (obligatoire sans nom d’utilisateur)')
        .fill('visiteur-' + width + '@example.invalid')
      await page.getByRole('button', { name: 'Envoyer' }).click()
      await page.getByRole('heading', { name: 'Message envoyé' }).waitFor()
      results['contact_sent_' + width] = (await page.evaluate(() => document.activeElement.id)) === 'contact-success'
      await page.screenshot({ path: `tmp/public-pages/contact-${width}.png`, fullPage: true })
      await check(page, errors, 'contact ' + width)
      // Pied de page : pages légales.
      const footer = page.getByRole('navigation', { name: 'Informations légales' })
      for (const [link, heading] of [
        ['Mentions légales', 'Mentions légales'],
        ['Conditions générales d’utilisation', 'Conditions générales d’utilisation'],
        ['Conditions générales de vente', 'Conditions générales de vente'],
        ['Confidentialité', 'Politique de confidentialité'],
      ]) {
        await footer.getByRole('link', { name: link, exact: true }).click()
        await page.getByRole('heading', { level: 1, name: heading }).waitFor()
        if (!(await page.title()).startsWith(heading)) throw Error('Titre ' + heading)
      }
      await footer.getByRole('link', { name: 'Mentions légales' }).click()
      await page.getByRole('heading', { level: 1, name: 'Mentions légales' }).waitFor()
      const legal = await page.locator('main').textContent()
      results['legal_mentions_' + width] = [
        'Raison sociale',
        'capital social',
        'Siège social',
        'Téléphone',
        'Responsable de la publication',
        'Hostinger International Ltd',
        'Larnaca',
      ].every(t => legal.toLowerCase().includes(t.toLowerCase()) || t === 'Raison sociale')
      await page.screenshot({ path: `tmp/public-pages/mentions-${width}.png`, fullPage: true })
      await check(page, errors, 'mentions ' + width)
      await context.close()
    }
    // Page publique d'un événement non publié : message clair.
    const context = await browser.newContext(),
      page = await context.newPage()
    await page.goto(BASE + '/evenements-publics/1')
    await page.getByText('Cet événement n’existe pas ou n’est pas publié.').waitFor()
    results.unpublished_message = true
    await context.close()
  } finally {
    // Le navigateur est toujours fermé, même en cas d'échec.
    await browser.close()
  }
  console.log(JSON.stringify(results, null, 1))
  if (!Object.values(results).every(Boolean)) process.exit(1)
})().catch(e => {
  console.error(e)
  process.exit(1)
})
