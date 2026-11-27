const { test, expect } = require('@playwright/test');

const ORIGIN = 'https://localhost:18443';
const SLUG = '/noticias/cronica-ensenada-2';
const TITLE = 'Crónica sintética de Ensenada';
const DESCRIPTION = 'Una bajada para comprobar el HTML inicial.';
const ARTICLE_TEXT = 'Texto de la noticia visible antes de ejecutar JavaScript.';
const targets = [
  ['Laravel', ORIGIN],
  ...(process.env.NEWS_SPRING_SSR_ORIGIN ? [['Spring Boot', process.env.NEWS_SPRING_SSR_ORIGIN]] : []),
];

test.beforeEach(async ({ context }) => {
  await context.route('**/*', route => new URL(route.request().url()).hostname === 'localhost'
    ? route.continue() : route.abort('blockedbyclient'));
});

test('Angular lists public news and changes page using the existing controls', async ({ page }) => {
  await page.goto('/noticias');
  await expect(page.getByRole('heading', { name: 'Noticias de Ensenada' })).toBeVisible();
  await expect(page.locator('app-noticia-card')).toHaveCount(12);
  await expect(page.locator('app-noticia-card').first().getByRole('link', { name: 'Leer noticia' })).toHaveAttribute('href', SLUG);
  await expect(page.getByText('53 noticias publicadas')).toBeVisible();
  const pending = page.waitForResponse(response => {
    const url = new URL(response.url());
    return url.pathname === '/api/noticias' && url.searchParams.get('page') === '1';
  });
  await page.getByRole('button', { name: 'Siguiente' }).click();
  expect((await pending).status()).toBe(200);
  await expect(page.getByRole('button', { name: '2', exact: true })).toHaveAttribute('aria-current', 'page');
  await expect(page.locator('app-noticia-card').first()).toContainText('Noticia sintética 41');
  await expect(page.getByText(/Borrador privado|Archivo privado/)).toHaveCount(0);
});

for (const [backend, origin] of targets) {
  test(`${backend}: Angular follows public links and normalizes the historical URL after hydration`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.on('console', message => {
      if (message.type() === 'error' && /NG05\d+/.test(message.text())) errors.push(message.text());
    });

    await page.goto(origin);
    const featured = page.locator('app-noticias-destacadas');
    const lead = featured.getByRole('link', { name: 'Leer noticia principal' });
    await expect(lead).toHaveAttribute('href', SLUG);
    await expect(featured).toContainText(TITLE);
    await expect(featured.getByText(/Borrador privado|Archivo privado/)).toHaveCount(0);
    await page.evaluate(() => { window.ssrNavigationProbe = true; });
    await lead.click();
    await expect(page).toHaveURL(`${origin}${SLUG}`);
    await expect(page.getByRole('heading', { name: TITLE, exact: true })).toBeVisible();
    await expect(page.locator('head link[rel="canonical"]')).toHaveAttribute('href', `${origin}${SLUG}`);
    await page.getByRole('link', { name: 'Volver a noticias', exact: true }).first().click();
    await expect(page).toHaveURL(`${origin}/noticias`);
    await expect(page.getByRole('heading', { name: 'Noticias de Ensenada' })).toBeVisible();
    await expect(page.locator('head link[rel="canonical"], head meta[name="twitter:title"]')).toHaveCount(0);
    expect(await page.evaluate(() => window.ssrNavigationProbe)).toBe(true);

    const suffix = '?origen=historico#detalle';
    const initial = await page.goto(`${origin}/noticias/00063${suffix}`);
    expect(initial.status()).toBe(200);
    expect(initial.request().redirectedFrom()).toBeNull();
    await expect(page.getByRole('heading', { name: TITLE, exact: true })).toBeVisible();
    await expect(page).toHaveURL(`${origin}${SLUG}${suffix}`);
    await page.getByRole('button', { name: 'Ver foto 2' }).click();
    await expect(page.getByRole('img', { name: TITLE })).toHaveAttribute('src', '/assets/escudo-ensenada.png');

    expect((await page.goto(`${origin}/noticias/inexistente`)).status()).toBe(404);
    await expect(page.getByRole('heading', { name: 'Noticia no encontrada', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Volver a noticias', exact: true }).first().click();
    await expect(page.getByRole('heading', { name: 'Noticias de Ensenada' })).toBeVisible();
    expect(errors).toEqual([]);
  });
}

async function expectArticle(page, origin, path) {
  const response = await page.goto(`${origin}${path}`);
  expect(response.status()).toBe(200);
  expect(response.request().redirectedFrom()).toBeNull();
  expect(response.headers()['content-type']).toContain('text/html');
  expect(await response.text()).toContain(ARTICLE_TEXT);
  await expect(page).toHaveURL(`${origin}${path}`);
  await expect(page.getByRole('heading', { name: TITLE, exact: true })).toBeVisible();
  await expect(page.locator('main article')).toContainText(ARTICLE_TEXT);
  await expect(page.getByRole('heading', { name: 'Noticia no encontrada' })).toHaveCount(0);
  await expect(page).toHaveTitle(`${TITLE} | Municipalidad de Ensenada`);
  await expect(page.locator('head link[rel="canonical"]')).toHaveAttribute('href', `${origin}${SLUG}`);
  for (const [selector, value] of [
    ['name="description"', DESCRIPTION], ['property="og:title"', TITLE], ['property="og:description"', DESCRIPTION],
    ['property="og:type"', 'article'], ['property="og:url"', `${origin}${SLUG}`],
    ['property="og:image"', `${origin}/assets/ensenada-hero.jpg`], ['property="article:published_time"', '2099-04-05'],
    ['name="twitter:card"', 'summary_large_image'], ['name="twitter:title"', TITLE],
    ['name="twitter:description"', DESCRIPTION], ['name="twitter:image"', `${origin}/assets/ensenada-hero.jpg`],
  ]) {
    await expect(page.locator(`head meta[${selector}]`)).toHaveAttribute('content', value);
  }
}

test.describe('HTML SSR sin JavaScript', () => {
  test.use({ javaScriptEnabled: false });

  test('SSR lists public links with the list title and no article metadata', async ({ page }) => {
    const list = await page.goto('/noticias');
    expect(list.status()).toBe(200);
    await expect(page).toHaveTitle('Noticias | Municipalidad de Ensenada');
    await expect(page.locator('app-noticia-card')).toHaveCount(12);
    await expect(page.locator('app-noticia-card').first().getByRole('link')).toHaveAttribute('href', SLUG);
    await expect(page.locator('head meta[property="og:type"][content="article"]')).toHaveCount(0);
  });

  for (const [backend, origin] of targets) {
    test(`${backend}: canonical slug and historical IDs return the complete article with status 200`, async ({ page }) => {
      for (const path of [SLUG, '/noticias/63', '/noticias/00063']) {
        await expectArticle(page, origin, path);
      }
    });

    test(`${backend}: missing, draft and archived news return a 404 document without article metadata`, async ({ page }) => {
      for (const identifier of ['inexistente', '9999999', 'borrador-privado', '61', 'archivo-privado', '62']) {
        expect((await page.goto(`${origin}/api/noticias/${identifier}`)).status()).toBe(404);
        const response = await page.goto(`${origin}/noticias/${identifier}`);
        expect(response.status()).toBe(404);
        expect(response.request().redirectedFrom()).toBeNull();
        expect(response.headers()['content-type']).toContain('text/html');
        const html = await response.text();
        expect(html).toContain('La noticia solicitada no existe o ya no se encuentra disponible.');
        expect(html).not.toMatch(/Borrador privado|Archivo privado|Texto de la noticia visible/);
        await expect(page.getByRole('heading', { name: 'Noticia no encontrada', exact: true })).toBeVisible();
        await expect(page.locator('main article')).toHaveCount(0);
        await expect(page).toHaveTitle('Noticia no encontrada | Municipalidad de Ensenada');
        await expect(page.locator('head meta[property="og:type"]')).toHaveAttribute('content', 'website');
        await expect(page.locator('head meta[property="og:title"]')).toHaveAttribute('content', 'Municipalidad de Ensenada');
        await expect(page.locator(
          'head link[rel="canonical"], head meta[property="og:url"], head meta[property="og:image"], head meta[property^="article:"], head meta[name^="twitter:"]',
        )).toHaveCount(0);
      }
      await expectArticle(page, origin, SLUG);
    });
  }
});
