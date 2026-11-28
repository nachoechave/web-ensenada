const { test, expect } = require('@playwright/test');
const mysql = require('mysql2/promise');
const { readFileSync } = require('node:fs');

const ORIGIN = 'https://localhost:18443';
const ADMIN = '/api/admin/hacienda';
const PUBLIC = '/api/hacienda';
const DRAFT = 'Borrador sintético de Chromium';
const TITLE = 'Presupuesto sintético de Chromium Ñandú';

async function guardNetwork(context) {
  await context.route('**/*', route => new URL(route.request().url()).hostname === 'localhost'
    ? route.continue() : route.abort('blockedbyclient'));
}

test.beforeEach(async ({ context }) => {
  await guardNetwork(context);
});

function responseFor(page, path, method) {
  return page.waitForResponse(response => new URL(response.url()).pathname === path && response.request().method() === method);
}

function samplePdf() {
  const objects = [
    '<< /Type /Catalog /Pages 2 0 R >>',
    '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
    '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 300] >>',
  ];
  let content = '%PDF-1.4\n';
  const offsets = objects.map((object, index) => {
    const offset = content.length;
    content += `${index + 1} 0 obj\n${object}\nendobj\n`;
    return `${String(offset).padStart(10, '0')} 00000 n \n`;
  });
  return Buffer.from(content + `xref\n0 4\n0000000000 65535 f \n${offsets.join('')}trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n${content.length}\n%%EOF\n`);
}

async function login(page, role) {
  await page.goto('/admin/login');
  await page.getByLabel('Email', { exact: true }).fill(role+'@example.test');
  await page.getByLabel('Contraseña', { exact: true }).fill('Synthetic-password-1234');
  const pending = responseFor(page, '/api/auth/login', 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  expect((await pending).status()).toBe(200);
  await expect(page).toHaveURL(ORIGIN+'/admin');
}

async function database() {
  if (process.env.AUTH_TEST_MYSQL_HOST !== 'mysql-auth') throw new Error('Solo se admite el MySQL descartable');
  return mysql.createConnection({
    host: 'mysql-auth', port: 3306, database: 'ensenada_auth_test',
    user: 'auth_setup', password: 'synthetic-setup-only-1234',
  });
}

test('HACIENDA completa el circuito real de documentos públicos y privados desde Angular', async ({ page, browser }) => {
  const db = await database();
  const anonymous = await browser.newContext({ baseURL: ORIGIN });
  let id;
  try {
    await db.execute('DELETE FROM auth_login_windows');
    await guardNetwork(anonymous);
    const publicPage = await anonymous.newPage();
    await login(page, 'hacienda');
    await page.getByRole('link', { name: 'Hacienda', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Publicaciones de Hacienda' })).toBeVisible();
    await expect(page.getByRole('row', { name: /Borrador sintético reservado/ })).toBeVisible();
    await expect(page.getByRole('row', { name: /Archivo sintético reservado/ })).toBeVisible();
    await page.getByRole('link', { name: '+ Nueva publicación', exact: true }).click();
    await page.getByLabel('Título', { exact: true }).fill(DRAFT);
    await page.getByLabel('Descripción', { exact: true }).fill('Contenido sintético de Chromium.');
    await page.locator('select[name="tipo"]').selectOption('PRESUPUESTO');
    await page.getByLabel('Fecha de publicación', { exact: true }).fill('2026-06-02');
    await expect.poll(() => page.locator('option[value="PUBLICADA"]').evaluate(option => option.disabled)).toBe(true);
    const created = responseFor(page, ADMIN, 'POST').then(async response => {
      expect(response.status()).toBe(201);
      return response.json();
    });
    await page.getByRole('button', { name: 'Guardar borrador' }).click();
    const draft = await created;
    id = draft.id;
    expect(draft).toMatchObject({ titulo: DRAFT, estado: 'BORRADOR', archivos: [] });
    await expect(page).toHaveURL(ORIGIN+'/admin/hacienda/editar/'+id);
    await expect(page.getByText('Todavía no hay archivos')).toBeVisible();

    const publicList = await publicPage.goto('/hacienda');
    expect(publicList.status()).toBe(200);
    await expect(publicPage.getByRole('link', { name: 'Informe público sintético' })).toBeVisible();
    await expect(publicPage.getByText(/Borrador sintético reservado|Archivo sintético reservado|Borrador sintético de Chromium/)).toHaveCount(0);
    await publicPage.goto('/hacienda/'+id);
    await expect(publicPage.getByRole('heading', { name: 'Publicación no disponible' })).toBeVisible();

    const filesPath = `${ADMIN}/${id}/archivos`;
    const pdfName = 'Documento sintético de Chromium.pdf';
    const pngName = 'Imagen sintética de Chromium.png';
    const pdfBytes = samplePdf();
    const png = await page.evaluate(() => {
      const canvas = document.createElement('canvas');
      canvas.width = canvas.height = 12;
      return canvas.toDataURL('image/png').split(',')[1];
    });
    const input = page.locator('.documents-panel input[type=file]');
    const upload = async (name, mimeType, buffer) => {
      const pending = responseFor(page, filesPath, 'POST');
      await input.setInputFiles({ name, mimeType, buffer });
      const response = await pending;
      expect(response.status()).toBe(201);
      await expect(page.getByText(name, { exact: true })).toBeVisible();
      return (await response.json()).archivos.at(-1);
    };
    const pdf = await upload(pdfName, 'application/pdf', pdfBytes);
    const image = await upload(pngName, 'image/png', Buffer.from(png, 'base64'));
    const publicStatus = url => publicPage.evaluate(async path => (await fetch(path)).status, url);
    expect(await publicStatus(pdf.url)).toBe(404);
    expect(await publicStatus(image.url)).toBe(404);
    const reorder = responseFor(page, `${filesPath}/orden`, 'PUT');
    await page.getByRole('button', { name: 'Subir '+pngName, exact: true }).click();
    const reordered = await reorder;
    expect(reordered.status()).toBe(200);
    expect((await reordered.json()).archivos.map(file => file.id)).toEqual([image.id, pdf.id]);
    await page.getByLabel('Título', { exact: true }).fill(TITLE);
    await page.locator('select[name="estado"]').selectOption('PUBLICADA');
    const updated = responseFor(page, ADMIN+'/'+id, 'PUT');
    await page.getByRole('button', { name: 'Guardar cambios' }).click();
    const publication = await updated;
    expect(publication.status()).toBe(200);
    const saved = await publication.json();
    expect(saved).toMatchObject({ id, titulo: TITLE, estado: 'PUBLICADA', archivos: [
      { url: image.url, tipoMime: 'image/png', orden: 0 },
      { url: pdf.url, tipoMime: 'application/pdf', orden: 1, tamanio: pdfBytes.length },
    ] });
    await expect(page.getByText('Publicación guardada.')).toBeVisible();

    await publicPage.goto('/hacienda');
    await publicPage.getByRole('link', { name: TITLE, exact: true }).click();
    await expect(publicPage).toHaveURL(ORIGIN+'/hacienda/'+id);
    await expect(publicPage.getByRole('heading', { name: TITLE })).toBeVisible();
    await expect(publicPage.getByRole('link', { name: 'Ver documento' })).toHaveAttribute('href', pdf.url);
    expect(await publicStatus(image.url)).toBe(200);
    const publicDownload = publicPage.waitForEvent('download');
    await publicPage.getByRole('link', { name: 'Descargar', exact: true }).click();
    expect(readFileSync(await (await publicDownload).path())).toEqual(pdfBytes);

    await page.getByRole('link', { name: 'Volver al listado' }).click();
    const row = page.getByRole('row', { name: new RegExp(TITLE) });
    const unpublish = responseFor(page, ADMIN+'/'+id, 'PUT');
    await row.getByRole('button', { name: 'Despublicar' }).click();
    expect((await unpublish).status()).toBe(200);
    await expect(row.getByText('BORRADOR')).toBeVisible();
    await publicPage.reload();
    await expect(publicPage.getByRole('heading', { name: 'Publicación no disponible' })).toBeVisible();
    expect(await publicStatus(pdf.url)).toBe(404);
    expect(await publicStatus(image.url)).toBe(404);

    await row.getByRole('link', { name: 'Editar' }).click();
    const privatePath = `${filesPath}/${pdf.id}/contenido`;
    const privateResponse = responseFor(page, privatePath, 'GET');
    const privateDownload = page.waitForEvent('download');
    await page.locator('.document-card').filter({ hasText: pdfName }).getByRole('button', { name: 'Revisar' }).click();
    expect((await privateResponse).status()).toBe(200);
    expect(readFileSync(await (await privateDownload).path())).toEqual(pdfBytes);
    page.once('dialog', dialog => dialog.accept());
    const removal = responseFor(page, `${filesPath}/${pdf.id}`, 'DELETE');
    await page.getByRole('button', { name: 'Retirar '+pdfName }).click();
    expect((await removal).status()).toBe(200);
    await expect(page.getByText(pdfName, { exact: true })).toHaveCount(0);
    expect(await page.evaluate(async path => (await fetch(path)).status, privatePath)).toBe(404);
    await page.getByRole('link', { name: 'Volver al listado' }).click();

    const republish = responseFor(page, ADMIN+'/'+id, 'PUT');
    await row.getByRole('button', { name: 'Publicar', exact: true }).click();
    expect((await republish).status()).toBe(200);
    await expect(row.getByText('PUBLICADA')).toBeVisible();
    expect(await publicStatus(image.url)).toBe(200);
    expect(await publicStatus(pdf.url)).toBe(404);
    page.once('dialog', dialog => dialog.accept());
    const archive = responseFor(page, ADMIN+'/'+id, 'DELETE');
    await row.getByRole('button', { name: 'Archivar' }).click();
    expect((await archive).status()).toBe(204);
    await expect(row.getByText('ARCHIVADA')).toBeVisible();
    await expect(row.locator('.file-count')).toHaveText('1');
    const archived = await publicPage.reload();
    const html = await archived.text();
    expect(html).not.toContain(TITLE);
    expect(html).not.toContain(image.url);
    await expect(publicPage.getByRole('heading', { name: 'Publicación no disponible' })).toBeVisible();
    expect(await publicPage.evaluate(async url => (await fetch(url)).status, PUBLIC+'/'+id)).toBe(404);
    expect(await publicStatus(image.url)).toBe(404);
  } finally {
    if (id) {
      await db.execute('DELETE FROM archivos_hacienda WHERE publicacion_hacienda_id=?', [id]);
      await db.execute('DELETE FROM publicaciones_hacienda WHERE id=?', [id]);
    }
    await anonymous.close();
    await db.end();
  }
});

test('SUPER_ADMIN consulta Hacienda desde el menú', async ({ page }) => {
  await login(page, 'super_admin');
  await page.getByRole('link', { name: 'Hacienda', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Publicaciones de Hacienda' })).toBeVisible();
  await expect(page.getByRole('row', { name: /Borrador sintético reservado/ })).toBeVisible();
});

test('PRENSA recibe 403 incluso si solicita Hacienda directamente', async ({ page }) => {
  await login(page, 'prensa');
  await expect(page.getByRole('link', { name: 'Hacienda', exact: true })).toHaveCount(0);
  await page.goto('/admin/hacienda');
  await expect(page).toHaveURL(ORIGIN+'/admin');
  expect(await page.evaluate(async url => (await fetch(url)).status, ADMIN)).toBe(403);
});
