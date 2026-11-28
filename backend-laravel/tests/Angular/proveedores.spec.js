const { test, expect } = require('@playwright/test');
const mysql = require('mysql2/promise');

const admin = '/api/admin/proveedores/documentos';
const pdf = Buffer.from('%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF\n');

function responseFor(page, path, method) {
  return page.waitForResponse(response => new URL(response.url()).pathname === path && response.request().method() === method);
}

test('Angular administra, ordena y retira documentos de Proveedores por HTTPS', async ({ page, browser }) => {
  if (process.env.AUTH_TEST_MYSQL_HOST !== 'mysql-auth') throw new Error('Solo se admite el MySQL descartable');
  const db = await mysql.createConnection({ host: 'mysql-auth', database: 'ensenada_auth_test', user: 'auth_setup', password: 'synthetic-setup-only-1234' });
  const anonymous = await browser.newContext({ baseURL: 'https://localhost:18443' });
  const created = [];
  const guard = context => context.route('**/*', route => new URL(route.request().url()).hostname === 'localhost' ? route.continue() : route.abort());
  try {
    await guard(page.context());
    await guard(anonymous);
    await db.execute('DELETE FROM auth_login_windows');
    await page.goto('/admin/login');
    await page.getByLabel('Email', { exact: true }).fill('hacienda@example.test');
    await page.getByLabel('Contraseña', { exact: true }).fill('Synthetic-password-1234');
    const login = responseFor(page, '/api/auth/login', 'POST');
    await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
    expect((await login).status()).toBe(200);
    await expect(page).toHaveURL(/\/admin$/);
    await page.goto('/admin/proveedores');
    const form = page.locator('.upload-card');
    for (const title of ['Requisitos sintéticos', 'Formulario sintético']) {
      await form.getByLabel('Título').fill(title);
      await form.getByLabel('Descripción').fill('Prueba del portal');
      await form.locator('input[type=file]').setInputFiles({ name: `${title}.pdf`, mimeType: 'application/pdf', buffer: pdf });
      const pending = responseFor(page, admin, 'POST');
      await form.getByRole('button', { name: '+ Cargar documento' }).click();
      const response = await pending;
      expect(response.status()).toBe(201);
      created.push(await response.json());
      await expect(page.getByText(`${title}.pdf`, { exact: true })).toBeVisible();
    }
    const [first, second] = created;
    const editor = page.locator('.document-editor').filter({ hasText: 'Formulario sintético.pdf' });
    const order = responseFor(page, `${admin}/orden`, 'PUT');
    await editor.getByRole('button', { name: '↑' }).click();
    const sorted = await order;
    expect(sorted.status()).toBe(200);
    expect((await sorted.json()).map(row => row.id)).toEqual([second.id, first.id]);

    const publicPage = await anonymous.newPage();
    await publicPage.goto('/proveedores');
    const cards = publicPage.locator('.document-card');
    await expect(cards.first()).toContainText(second.titulo);
    await expect(cards.first()).toHaveAttribute('href', second.url);
    const download = await publicPage.evaluate(async url => {
      const response = await fetch(url);
      return { status: response.status, bytes: Array.from(new Uint8Array(await response.arrayBuffer())), disposition: response.headers.get('content-disposition') };
    }, second.url);
    expect(download.status).toBe(200);
    expect(Buffer.from(download.bytes)).toEqual(pdf);
    expect(download.disposition).toContain('attachment');

    await editor.getByLabel('Visible en la página pública').uncheck();
    const update = responseFor(page, `${admin}/${second.id}`, 'PUT');
    await editor.getByRole('button', { name: 'Guardar' }).click();
    expect((await update).status()).toBe(200);
    await publicPage.reload();
    await expect(cards).toHaveCount(1);
    await expect(cards.first()).toContainText(first.titulo);
    expect(await publicPage.evaluate(async url => (await fetch(url)).status, second.url)).toBe(404);
    page.once('dialog', dialog => dialog.accept());
    const remove = responseFor(page, `${admin}/${second.id}`, 'DELETE');
    await editor.getByRole('button', { name: 'Eliminar' }).click();
    expect((await remove).status()).toBe(204);
    await expect(editor).toHaveCount(0);
  } finally {
    for (const row of created) await db.execute('DELETE FROM documentos_proveedores WHERE id=?', [row.id]);
    await anonymous.close();
    await db.end();
  }
});
