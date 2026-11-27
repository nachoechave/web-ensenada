const { test, expect } = require('@playwright/test');
const { randomUUID } = require('node:crypto');
const mysql = require('mysql2/promise');

const API = '/api/admin/noticias';
const UPLOAD = '/api/admin/archivos/noticias';
const BASE = 'https://localhost:18443';
let db;
let id;
let title;

test.beforeEach(async ({ context, page }) => {
  if (process.env.AUTH_TEST_MYSQL_HOST !== 'mysql-auth') throw new Error('Disposable MySQL required');
  db = await mysql.createConnection({
    host: 'mysql-auth', port: 3306, database: 'ensenada_auth_test',
    user: 'auth_setup', password: 'synthetic-setup-only-1234',
  });
  id = undefined;
  title = `Editorial Chromium ${randomUUID()}`;
  await db.execute('DELETE FROM auth_login_windows');
  await context.route('**/*', route => new URL(route.request().url()).hostname === 'localhost'
    ? route.continue() : route.abort('blockedbyclient'));
  page.on('dialog', dialog => dialog.accept());
});

test.afterEach(async () => {
  if (!db) return;
  if (id) {
    await db.execute('DELETE FROM auditoria_noticias WHERE noticia_id = ?', [id]);
    await db.execute('DELETE FROM noticias WHERE id = ?', [id]);
  }
  await db.end();
  db = null;
});

function responseFor(page, method, pathname) {
  return page.waitForResponse(response => new URL(response.url()).pathname === pathname && response.request().method() === method);
}

async function login(page, role) {
  await page.goto('/admin/login');
  await page.getByLabel('Email', { exact: true }).fill(`${role}@example.test`);
  await page.getByLabel('Contraseña', { exact: true }).fill('Synthetic-password-1234');
  const response = responseFor(page, 'POST', '/api/auth/login');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  expect((await response).status()).toBe(200);
  await expect(page).toHaveURL(`${BASE}/admin`);
}

async function openList(page) {
  const response = responseFor(page, 'GET', API);
  await page.getByRole('link', { name: 'Noticias', exact: true }).click();
  expect((await response).status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Gestión de noticias' })).toBeVisible();
}

async function syntheticImage(page, mimeType) {
  return Buffer.from(await page.evaluate(async type => {
    const canvas = document.createElement('canvas');
    canvas.width = 32;
    canvas.height = 24;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = 'rgba(12,80,155,.6)';
    ctx.fillRect(0, 0, 32, 24);
    ctx.fillStyle = '#e79741';
    ctx.fillRect(2, 3, 14, 12);
    const blob = await new Promise(resolve => canvas.toBlob(resolve, type));
    return Array.from(new Uint8Array(await blob.arrayBuffer()));
  }, mimeType));
}

async function newsLifecycle({ page, context }, extension, mimeType) {
  await login(page, 'prensa');
  const cookie = (await context.cookies()).find(item => item.name === 'WEB_ENSENADA_SESSION');
  expect(cookie).toMatchObject({ secure: true, httpOnly: true, sameSite: 'Strict', path: '/api' });
  await openList(page);
  await page.getByRole('main').getByRole('link', { name: 'Nueva noticia' }).click();
  await expect(page.getByRole('heading', { name: 'Crear noticia' })).toBeVisible();
  await expect(page.getByText('Podés subir fotos de hasta 30 MB. Las optimizamos automáticamente para que carguen más rápido.')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Crear noticia' })).toBeDisabled();

  const upload = responseFor(page, 'POST', UPLOAD);
  await page.locator('input[type=file]').setInputFiles({ name: `sintetica.${extension}`, mimeType, buffer: await syntheticImage(page, mimeType) });
  const uploaded = await upload;
  expect(uploaded.status()).toBe(200);
  const image = await uploaded.json();
  expect(image).toMatchObject({ contentType: 'image/webp', size: expect.any(Number) });
  expect(image.url).toBe(`/uploads/noticias/${image.nombreOriginal}`);
  expect(image.url).toMatch(/^\/uploads\/noticias\/[0-9a-f-]{36}\.webp$/);
  await expect(page.getByText('Portada', { exact: true })).toBeVisible();
  await page.getByLabel('Título').fill(title);
  await page.getByLabel('Bajada').fill('Bajada de prueba');
  await page.getByLabel('Contenido', { exact: true }).fill('Contenido de prueba');
  await page.getByLabel('Categoría').selectOption('Cultura');
  await page.getByLabel('Fecha de publicación').fill('2026-04-07');
  const create = responseFor(page, 'POST', API);
  await page.getByRole('button', { name: 'Crear noticia' }).click();
  const creation = await create;
  expect(creation.status()).toBe(201);
  const created = await creation.json();
  expect(created).toMatchObject({ imagen: image.url, imagenes: [image.url], estado: 'BORRADOR' });
  id = created.id;
  const item = `${API}/${id}`;
  await expect(page).toHaveURL(`${BASE}/admin/noticias`);
  const row = page.getByRole('row').filter({ hasText: title });
  const publishing = responseFor(page, 'PUT', item);
  await row.getByRole('button', { name: 'Publicar', exact: true }).click();
  expect((await publishing).status()).toBe(200);
  await expect(row.getByText('PUBLICADA', { exact: true })).toBeVisible();

  const publicPage = await context.newPage();
  try {
    const publicDownload = responseFor(publicPage, 'GET', image.url);
    await publicPage.goto('/noticias');
    const card = publicPage.locator('app-noticia-card').filter({ hasText: title });
    await expect(card).toBeVisible();
    const download = await publicDownload;
    expect(download.status()).toBe(200);
    expect(download.headers()['content-type']).toBe('image/webp');
    expect((await download.body()).length).toBe(image.size);
    await card.getByRole('link', { name: 'Leer noticia' }).click();
    await expect(publicPage).toHaveURL(`${BASE}/noticias/${created.slug}`);
    await expect(publicPage.getByRole('heading', { name: title, exact: true })).toBeVisible();
    await expect.poll(() => publicPage.getByRole('img', { name: title, exact: true }).evaluate(img => img.naturalWidth)).toBe(32);
  } finally {
    await publicPage.close();
  }

  const detail = responseFor(page, 'GET', item);
  await row.getByRole('link', { name: 'Editar' }).click();
  expect((await detail).status()).toBe(200);
  await expect(page.getByLabel('Título')).toHaveValue(title);
  await page.getByLabel('Título').fill(`${title} editada`);
  await page.getByLabel('Mostrar en noticias destacadas').check();
  const saved = responseFor(page, 'PUT', item);
  await page.getByRole('button', { name: 'Guardar cambios' }).click();
  const edited = await saved;
  expect(edited.status()).toBe(200);
  expect(await edited.json()).toMatchObject({ slug: created.slug, imagen: image.url, destacada: true, fechaPublicacion: '2026-04-07' });
  await expect(page).toHaveURL(`${BASE}/admin/noticias`);

  for (const [button, state] of [['Despublicar', 'BORRADOR'], ['Archivar', 'ARCHIVADA'], ['Publicar', 'PUBLICADA']]) {
    const archiving = state === 'ARCHIVADA';
    const pending = responseFor(page, archiving ? 'DELETE' : 'PUT', item);
    await row.getByRole('button', { name: button, exact: true }).click();
    expect((await pending).status()).toBe(archiving ? 204 : 200);
    await expect(row.getByText(state, { exact: true })).toBeVisible();
  }

  const audit = responseFor(page, 'GET', `${API}/auditoria`);
  await page.getByRole('main').getByRole('link', { name: 'Historial editorial' }).click();
  const response = await audit;
  expect(response.status()).toBe(200);
  const events = (await response.json()).filter(event => event.noticiaId === id);
  expect(events.map(event => event.accion)).toEqual(['PUBLICACION', 'ARCHIVADO', 'DESPUBLICACION', 'EDICION', 'PUBLICACION', 'CREACION']);
  expect(events.every(event => event.usuarioEmail === 'prensa@example.test')).toBe(true);
  await expect(page.getByRole('row').filter({ hasText: `${title} editada` }).first()).toContainText('PUBLICACION');
}

for (const [extension, mimeType] of [['jpg', 'image/jpeg'], ['png', 'image/png'], ['webp', 'image/webp']]) {
  test(`PRENSA completes the Angular news form and serves a recoded ${extension} from the public portal`,
    ({ page, context }) => newsLifecycle({ page, context }, extension, mimeType));
}

test('Angular rejects disguised HTML and a corrupt JPEG from the real form', async ({ page }) => {
  await login(page, 'prensa');
  await openList(page);
  await page.getByRole('main').getByRole('link', { name: 'Nueva noticia' }).click();
  const input = page.locator('input[type=file]');
  let uploads = 0;
  page.on('request', request => {
    if (new URL(request.url()).pathname === UPLOAD) uploads++;
  });
  await input.setInputFiles({ name: 'foto.heic', mimeType: 'image/heic', buffer: Buffer.from('sintético') });
  await expect(page.getByText('Seleccioná una imagen JPEG, PNG o WebP.')).toBeVisible();
  await input.setInputFiles({ name: 'grande.jpg', mimeType: 'image/jpeg', buffer: Buffer.alloc(30 * 1024 * 1024 + 1) });
  await expect(page.getByText('La imagen supera el límite de 30 MB.')).toBeVisible();
  await input.setInputFiles(Array.from({ length: 7 }, (_, index) => ({ name: `${index}.png`, mimeType: 'image/png', buffer: Buffer.from('sintético') })));
  await expect(page.getByText('Podés cargar hasta 6 imágenes por noticia.')).toBeVisible();
  expect(uploads).toBe(0);
  for (const file of [
    { name: 'falsa.png', mimeType: 'image/png', buffer: Buffer.from('<html><script>alert(1)</script></html>') },
    { name: 'truncada.jpg', mimeType: 'image/jpeg', buffer: (await syntheticImage(page, 'image/jpeg')).subarray(0, 100) },
  ]) {
    const pending = responseFor(page, 'POST', UPLOAD);
    await page.locator('input[type=file]').setInputFiles(file);
    expect((await pending).status()).toBe(400);
    await expect(page.getByText(/dañad[oa]/).first()).toBeVisible();
    await expect(page.getByRole('button', { name: 'Crear noticia' })).toBeDisabled();
  }
});

test('SUPER_ADMIN opens the editorial list and audit through Angular', async ({ page }) => {
  await login(page, 'super_admin');
  await openList(page);
  const audit = responseFor(page, 'GET', `${API}/auditoria`);
  await page.getByRole('main').getByRole('link', { name: 'Historial editorial' }).click();
  expect((await audit).status()).toBe(200);
  await expect(page.getByRole('heading', { name: 'Historial editorial' })).toBeVisible();
});

test('HACIENDA cannot open the editorial list even with forged local role data', async ({ page }) => {
  await login(page, 'hacienda');
  await expect(page.getByRole('link', { name: 'Noticias', exact: true })).toHaveCount(0);
  await page.evaluate(() => {
    const user = JSON.parse(sessionStorage.getItem('admin-user'));
    sessionStorage.setItem('admin-user', JSON.stringify({ ...user, rol: 'PRENSA', roles: ['PRENSA'] }));
  });
  const pending = responseFor(page, 'GET', API);
  await page.goto('/admin/noticias');
  expect((await pending).status()).toBe(403);
  await expect(page).toHaveURL(`${BASE}/admin/login`);
});
