const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { mkdirSync, existsSync } = require('node:fs');
const { chromium, expect } = require('/tests/node_modules/@playwright/test');

const host = 'prueba.ensenada.gov.ar';
const origin = 'https://' + host;
assert(existsSync('/.dockerenv') && process.env.IW_FINAL_TEST === 'yes');
const nss = `${process.env.HOME}/.pki/nssdb`;
mkdirSync(nss, { recursive: true });
execFileSync('certutil', ['-N', '-d', 'sql:' + nss, '--empty-password']);
execFileSync('certutil', ['-A', '-d', 'sql:' + nss, '-n', 'staging-fixture', '-t', 'C,,', '-i', '/tmp/staging-ca.crt']);

(async () => {
  const browser = await chromium.launch({ args: ['--no-sandbox', '--disable-background-networking'] });
  try {
    const context = await browser.newContext();
    await context.route('**/*', (route) => new URL(route.request().url()).hostname === host
      ? route.continue() : route.abort('blockedbyclient'));
    const page = await context.newPage();
    for (const path of ['/', '/noticias', '/noticias/noticia-visible', '/areas', '/intendencia', '/hacienda', '/proveedores']) {
      const response = await page.goto(origin + path);
      assert.equal(response.status(), 200, path);
      await expect(page.locator('main h1')).toBeVisible();
    }
    await page.goto(origin + '/admin/login');
    const login = page.waitForResponse((response) => response.url() === origin + '/api/auth/login'
      && response.request().method() === 'POST');
    await page.getByLabel('Email', { exact: true }).fill('prensa@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('Synthetic-password-1234');
    await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
    const response = await login;
    assert.equal(response.status(), 200);
    assert.equal(await response.request().headerValue('origin'), origin);
    // The bridge verifies Fetch Metadata before it can return this successful response.
    await expect(page).toHaveURL(origin + '/admin');
    const cookies = await context.cookies();
    const session = cookies.find(({ name }) => name === 'WEB_ENSENADA_SESSION');
    const preview = cookies.find(({ name }) => name === '__Secure-WEB_ENSENADA_IMAGE_PREVIEW');
    assert(session?.secure && session.httpOnly && session.sameSite === 'Strict' && session.path === '/api');
    assert(preview?.secure && preview.httpOnly && preview.sameSite === 'Strict' && preview.path === '/uploads');
    const responseNews = page.waitForResponse((response) => response.url() === origin + '/api/admin/noticias');
    await page.getByRole('link', { name: 'Noticias', exact: true }).click();
    assert.equal((await responseNews).status(), 200);
    await expect(page).toHaveURL(origin + '/admin/noticias');
    console.log('Chromium HTTPS: navegación anónima y login PRENSA desde Angular real, sin Basic.');
  } finally {
    await browser.close();
  }
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
