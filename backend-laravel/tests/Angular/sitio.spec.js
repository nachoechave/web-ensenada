const { test, expect } = require('@playwright/test');
const mysql = require('mysql2/promise');
const { mkdirSync, readFileSync, writeFileSync } = require('node:fs');
const fixture = require('./site-content.json');

const PUBLIC = '/api/site-content';
const ADMIN = '/api/admin/site-content';
const UPLOAD = '/api/admin/archivos/sitio';
const HERO = 'Portada Ñandú <em>edición</em> </script><script>window.siteContentProbe=1</script> 😀';
const AREA = 'Directorio sintético actualizado';
const OFFICE = 'Sede Ñandú 100 & 101';
const SLIDE = '/assets/site-synthetic-third.png';
const HISTORY = 'Historia sintética actualizada';
const imageReport = process.env.SITE_IMAGE_MANIFEST ?? '/results/stage5b/installed-images.json';

async function database() {
  if (process.env.AUTH_TEST_MYSQL_HOST !== 'mysql-auth') throw new Error('Solo se admite el MySQL descartable');
  return mysql.createConnection({
    host: 'mysql-auth', port: 3306, database: 'ensenada_auth_test',
    user: 'auth_setup', password: 'synthetic-setup-only-1234',
  });
}

function wire(page, path, method) {
  return page.waitForResponse(response => new URL(response.url()).pathname === path && response.request().method() === method);
}

async function openEditor(page) {
  await page.goto('/admin/login');
  await page.getByLabel('Email', { exact: true }).fill('super_admin@example.test');
  await page.getByLabel('Contraseña', { exact: true }).fill('Synthetic-password-1234');
  const login = wire(page, '/api/auth/login', 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  expect((await login).status()).toBe(200);
  const content = wire(page, ADMIN, 'GET');
  await page.getByRole('link', { name: 'Sitio público', exact: true }).click();
  await expect(page).toHaveURL(/\/admin\/sitio$/);
  await expect(page.getByRole('heading', { name: 'Control total de la portada' })).toBeVisible();
  expect((await content).status()).toBe(200);
  return page.locator('.site-editor');
}

async function section(editor, name) {
  const group = editor.locator('details').filter({ has: editor.page().getByText(name, { exact: true }) });
  await group.locator('summary').click();
  return group;
}

async function publicAreas(page) {
  const response = wire(page, PUBLIC, 'GET');
  await page.goto('/areas');
  expect((await response).status()).toBe(200);
  await expect(page.getByRole('heading', { name: AREA })).toBeVisible();
  await expect(page.locator('#area-sintetica')).toContainText(OFFICE);
}

async function fileFor(page, mime) {
  const data = await page.evaluate(type => {
    const canvas = document.createElement('canvas');
    canvas.width = 160;
    canvas.height = 100;
    const context = canvas.getContext('2d');
    context.fillStyle = type === 'image/png' ? 'rgba(12,80,120,.5)' : '#125078';
    context.fillRect(0, 0, 120, 100);
    return canvas.toDataURL(type).split(',')[1];
  }, mime);
  return { name: mime === 'image/png' ? 'sintetica.png' : 'sintetica.jpg', mimeType: mime, buffer: Buffer.from(data, 'base64') };
}

async function download(page, image) {
  const received = await page.evaluate(async url => {
    const response = await fetch(url, { cache: 'no-store' });
    const bytes = await response.arrayBuffer();
    const digest = await crypto.subtle.digest('SHA-256', bytes);
    return {
      status: response.status,
      type: response.headers.get('content-type'),
      size: bytes.byteLength,
      hash: Array.from(new Uint8Array(digest), byte => byte.toString(16).padStart(2, '0')).join(''),
    };
  }, image.url);
  expect(received).toMatchObject({ status: 200, type: image.contentType, size: image.size });
  return received.hash;
}

async function renderedImages(page, hero, gallery) {
  const heroDownload = wire(page, hero.url, 'GET');
  await page.goto('/');
  await expect(page.locator(`.hero-slide[style*="${hero.url}"]`)).toHaveCount(1);
  expect((await heroDownload).status()).toBe(200);

  const galleryDownload = wire(page, gallery.url, 'GET');
  await page.goto('/intendencia');
  const image = page.locator(`.galeria-grid img[src="${gallery.url}"]`);
  await image.scrollIntoViewIfNeeded();
  expect((await galleryDownload).status()).toBe(200);
  await expect.poll(() => image.evaluate(element => [element.naturalWidth, element.naturalHeight])).toEqual([160, 100]);
}

test.beforeEach(async ({ context }) => {
  await context.route('**/*', route => new URL(route.request().url()).hostname === 'localhost'
    ? route.continue() : route.abort('blockedbyclient'));
});

if (process.env.SITE_RESTART_CHECK === '1') {
  test('contenido institucional después del reinicio', async ({ page }) => {
    const editor = await openEditor(page);
    await expect((await section(editor, 'Hero principal y carrusel')).getByLabel('Título', { exact: true })).toHaveValue(HERO);
    await expect((await section(editor, 'Áreas municipales')).getByLabel('Título de /areas')).toHaveValue(AREA);
    await publicAreas(page);
    expect((await page.goto('/')).status()).toBe(200);
    await expect(page.getByRole('heading', { name: HERO })).toBeVisible();
  });
} else if (process.env.SITE_IMAGES_RESTART_CHECK === '1') {
  test('imágenes institucionales después del reinicio', async ({ page }) => {
    const [hero, gallery] = JSON.parse(readFileSync(imageReport, 'utf8'));
    const editor = await openEditor(page);
    await expect((await section(editor, 'Hero principal y carrusel')).locator(`img[src="${hero.url}"]`)).toHaveCount(1);
    await expect((await section(editor, 'Intendencia / Municipio')).locator(`img[src="${gallery.url}"]`)).toHaveCount(1);
    await renderedImages(page, hero, gallery);
    for (const image of [hero, gallery]) expect(await download(page, image)).toBe(image.hash);
  });
} else {
  test('SUPER_ADMIN edita, guarda, relee y publica el contenido institucional desde Angular', async ({ page, context }) => {
    const cdp = await context.newCDPSession(page);
    await cdp.send('Network.enable');
    let updateRequest;
    const metadata = new Map();
    cdp.on('Network.requestWillBeSent', event => {
      if (new URL(event.request.url).pathname === ADMIN && event.request.method === 'PUT') updateRequest = event.requestId;
    });
    cdp.on('Network.requestWillBeSentExtraInfo', event => {
      const headers = Object.fromEntries(Object.entries(event.headers).map(([key, value]) => [key.toLowerCase(), value]));
      metadata.set(event.requestId, {
        site: headers['sec-fetch-site'],
        hasSessionCookie: (headers.cookie ?? '').includes('WEB_ENSENADA_SESSION='),
      });
    });
    const db = await database();
    try {
      await db.execute('UPDATE portal_site_content SET contenido_json=? WHERE id=1', [JSON.stringify(fixture)]);
      await db.execute('DELETE FROM auth_login_windows');
    } finally {
      await db.end();
    }
    const editor = await openEditor(page);
    const top = await section(editor, 'Barra superior y navegación');
    await top.getByLabel('Dirección', { exact: true }).fill('Topbar sintética actualizada');
    await top.getByLabel('Facebook', { exact: true }).fill('https://social.example.test/actualizado');
    await top.getByLabel('Texto', { exact: true }).first().fill('Inicio sintético');
    const hero = await section(editor, 'Hero principal y carrusel');
    await hero.getByLabel('Título', { exact: true }).fill(HERO);
    await hero.getByLabel('CTA principal', { exact: true }).fill('Acción sintética');
    await hero.getByLabel('Agregar por URL').fill(SLIDE);
    await hero.getByRole('button', { name: 'Agregar URL' }).click();
    await expect(hero.locator('.hero-image-item')).toHaveCount(3);
    const links = await section(editor, 'Accesos rápidos');
    await links.getByLabel('Título del bloque').fill('Accesos sintéticos actualizados');
    const history = await section(editor, 'Intendencia / Municipio');
    await history.getByLabel('Título de historia').fill(HISTORY);
    const news = await section(editor, 'Noticias destacadas');
    await news.getByLabel('Título', { exact: true }).fill('Actualidad sintética actualizada');
    const areas = await section(editor, 'Áreas municipales');
    await areas.getByLabel('Título de /areas').fill(AREA);
    await areas.locator('input[data-secretaria-id="area-sintetica"]').fill(OFFICE);
    const agenda = await section(editor, 'Agenda y eventos');
    await agenda.getByLabel('Título', { exact: true }).first().fill('Agenda sintética actualizada');
    const footer = await section(editor, 'Footer y contacto');
    await footer.getByLabel('Email', { exact: true }).fill('actualizado@example.test');

    const written = wire(page, ADMIN, 'PUT');
    const reloaded = wire(page, ADMIN, 'GET');
    await editor.getByRole('button', { name: 'Guardar y publicar' }).first().click();
    const put = await written;
    expect(put.status()).toBe(200);
    await expect.poll(() => metadata.get(updateRequest)).toEqual({ site: 'same-origin', hasSessionCookie: true });
    const sent = put.request().postDataJSON();
    expect(sent.extensionSintetica).toEqual(fixture.extensionSintetica);
    expect(sent.hero.imagenes).toEqual([...fixture.hero.imagenes, SLIDE]);
    expect(sent.tramites).toEqual(fixture.tramites);
    expect(sent.intendencia.galeria).toEqual(fixture.intendencia.galeria);
    expect(await put.json()).toEqual(sent);
    const get = await reloaded;
    expect(get.status()).toBe(200);
    expect(await get.json()).toEqual(sent);
    await expect(editor.getByText('Cambios guardados y verificados contra la base de datos.')).toBeVisible();
    const reopened = wire(page, ADMIN, 'GET');
    await page.reload();
    expect(await (await reopened).json()).toEqual(sent);

    const home = await page.goto('/');
    expect(home.status()).toBe(200);
    await expect(page.getByRole('heading', { name: HERO })).toBeVisible();
    await expect(page.locator('#hero-title em')).toHaveCount(0);
    expect(await page.evaluate(() => window.siteContentProbe)).toBeUndefined();
    await expect(page.getByRole('link', { name: /Acción sintética/ })).toHaveAttribute('href', '#accesos');
    await expect(page.locator('.hero-slide')).toHaveCount(3);
    await expect(page.locator('.hero-slide').nth(2)).toHaveCSS('background-image', /site-synthetic-third\.png/);
    await page.getByRole('button', { name: 'Mostrar imagen 3', exact: true }).click();
    await expect(page.locator('.hero-slide.active')).toHaveCSS('background-image', /site-synthetic-third\.png/);
    await expect(page.getByText('Topbar sintética actualizada')).toBeVisible();
    await expect(page.getByRole('link', { name: 'Inicio sintético', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Facebook' }).first()).toHaveAttribute('href', 'https://social.example.test/actualizado');
    await expect(page.getByRole('heading', { name: 'Accesos sintéticos actualizados' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Actualidad sintética actualizada' })).toBeVisible();
    await expect(page.getByRole('link', { name: 'actualizado@example.test' })).toHaveAttribute('href', 'mailto:actualizado@example.test');
    await page.goto('/intendencia');
    await expect(page.getByRole('heading', { name: HISTORY })).toBeVisible();
    await expect(page.locator('.galeria-grid img')).toHaveAttribute('src', fixture.intendencia.galeria[0]);
    await publicAreas(page);
  });

  test('Áreas espera una respuesta tardía del backend y luego muestra el directorio', async ({ page }) => {
    let release;
    const hold = new Promise(resolve => { release = resolve; });
    await page.route('**/api/site-content', async route => {
      await hold;
      await route.continue();
    });
    const response = wire(page, PUBLIC, 'GET');
    try {
      await page.goto('/areas', { waitUntil: 'domcontentloaded' });
      await expect(page.getByRole('heading', { name: 'Cargando áreas municipales…' })).toBeVisible();
    } finally {
      release();
    }
    const delayed = await response;
    expect(delayed.status()).toBe(200);
    const published = await delayed.json();
    await expect(page.getByRole('heading', { name: published.areas.paginaTitulo })).toBeVisible();
    await expect(page.locator('#area-sintetica')).toContainText(published.areas.secretarias[0].direccion);
  });

  test('Áreas muestra el mensaje de error de una respuesta HTTP real y se recupera', async ({ page }) => {
    const db = await database();
    const [[original]] = await db.query('SELECT contenido_json,updated_at FROM portal_site_content WHERE id=1');
    try {
      await db.execute('UPDATE portal_site_content SET contenido_json=? WHERE id=1', ['{dato-sintetico-malformado}']);
      const response = wire(page, PUBLIC, 'GET');
      await page.goto('/areas');
      expect((await response).status()).toBe(500);
      await expect(page.getByRole('heading', { name: 'No pudimos cargar el directorio' })).toBeVisible();
      await expect(page.locator('.secretaria-card')).toHaveCount(0);
    } finally {
      await db.execute('UPDATE portal_site_content SET contenido_json=?,updated_at=? WHERE id=1', [original.contenido_json, original.updated_at]);
      await db.end();
    }
    await page.reload();
    await expect(page.getByRole('heading', { name: JSON.parse(original.contenido_json).areas.paginaTitulo })).toBeVisible();
  });

  test('SUPER_ADMIN carga imágenes institucionales desde el formulario y las publica', async ({ page }) => {
    const editor = await openEditor(page);
    const hero = await section(editor, 'Hero principal y carrusel');
    const heroInput = hero.getByLabel('Subir imagen', { exact: true });
    const invalid = wire(page, UPLOAD, 'POST');
    await heroInput.setInputFiles({ name: 'falsa.png', mimeType: 'image/png', buffer: Buffer.from('<svg onload="alert(1)"/>') });
    expect((await invalid).status()).toBe(400);
    await expect(editor.locator('.status.error')).toBeVisible();

    const jpeg = await fileFor(page, 'image/jpeg');
    const heroPending = wire(page, UPLOAD, 'POST');
    await heroInput.setInputFiles(jpeg);
    const heroResponse = await heroPending;
    expect(heroResponse.status()).toBe(200);
    const heroImage = await heroResponse.json();
    expect(heroImage).toMatchObject({ url: expect.stringMatching(/^\/uploads\/sitio\/[a-f0-9-]{36}\.webp$/), contentType: 'image/webp' });
    await expect(hero.locator(`img[src="${heroImage.url}"]`)).toHaveCount(1);

    const intendencia = await section(editor, 'Intendencia / Municipio');
    const png = await fileFor(page, 'image/png');
    const galleryPending = wire(page, UPLOAD, 'POST');
    await intendencia.getByLabel('Agregar foto a la galería', { exact: true }).setInputFiles(png);
    const galleryResponse = await galleryPending;
    expect(galleryResponse.status()).toBe(200);
    const gallery = await galleryResponse.json();
    expect(gallery).toMatchObject({ url: expect.stringMatching(/^\/uploads\/sitio\/[a-f0-9-]{36}\.webp$/), contentType: 'image/webp' });
    await expect(intendencia.locator(`img[src="${gallery.url}"]`)).toHaveCount(1);

    const saved = wire(page, ADMIN, 'PUT');
    const confirmed = wire(page, ADMIN, 'GET');
    await editor.getByRole('button', { name: 'Guardar y publicar' }).first().click();
    expect((await saved).status()).toBe(200);
    const get = await confirmed;
    expect(get.status()).toBe(200);
    const persisted = await get.json();
    expect(persisted.hero.imagenes).toContain(heroImage.url);
    expect(persisted.intendencia.galeria).toContain(gallery.url);
    await expect(editor.getByText('Cambios guardados y verificados contra la base de datos.')).toBeVisible();
    await renderedImages(page, heroImage, gallery);
    const manifest = [];
    for (const image of [heroImage, gallery]) manifest.push({ ...image, hash: await download(page, image) });
    mkdirSync(require('node:path').dirname(imageReport), { recursive: true });
    writeFileSync(imageReport, JSON.stringify(manifest));
  });

  for (const role of ['prensa', 'hacienda']) {
    test(`${role.toUpperCase()} no puede cargar una imagen institucional desde Chromium`, async ({ page }) => {
      await page.goto('/admin/login');
      await page.getByLabel('Email', { exact: true }).fill(role+'@example.test');
      await page.getByLabel('Contraseña', { exact: true }).fill('Synthetic-password-1234');
      const login = wire(page, '/api/auth/login', 'POST');
      await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
      expect((await login).status()).toBe(200);
      await expect(page).toHaveURL(/\/admin$/);
      await expect(page.getByRole('link', { name: 'Sitio público', exact: true })).toHaveCount(0);
      const png = await fileFor(page, 'image/png');
      const status = await page.evaluate(async ({ path, data }) => {
        const raw = Uint8Array.from(atob(data), char => char.charCodeAt(0));
        const body = new FormData();
        body.append('archivo', new File([raw], 'sintetica.png', { type: 'image/png' }));
        return (await fetch(path, { method: 'POST', body })).status;
      }, { path: UPLOAD, data: png.buffer.toString('base64') });
      expect(status).toBe(403);
    });
  }
}
