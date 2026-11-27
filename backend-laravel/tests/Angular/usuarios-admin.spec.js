const { test, expect } = require('@playwright/test');
const mysql = require('mysql2/promise');
const { randomUUID } = require('node:crypto');
const fixture = require('./java-bcrypt.json');

const BASE = 'https://localhost:18443';
const API = '/api/admin/usuarios';
const PASSWORD = 'Synthetic-password-1234';
const RESET_PASSWORD = 'Synthetic-browser-reset-5678';
const CONTRACT = ['activo', 'email', 'id', 'nombre', 'roles'];
let database;
let prefix;
let admin;

function address(tag) {
  return `${prefix}-${tag}@example.test`;
}

async function fixtureUser(tag, name, role, legacy = role) {
  const email = address(tag);
  const [row] = await database.execute(
    'INSERT INTO usuarios(nombre,email,password,rol,activo,token_version) VALUES(?,?,?,?,1,0)',
    [name, email, fixture.vectors[0].hash, legacy],
  );
  if (role) await database.execute('INSERT INTO usuario_roles(usuario_id,rol) VALUES(?,?)', [row.insertId, role]);
  return { id: row.insertId, email };
}

async function restrictNetwork(context) {
  await context.route('**/*', route => new URL(route.request().url()).hostname === 'localhost'
    ? route.continue() : route.abort('blockedbyclient'));
}

test.beforeEach(async ({ context }) => {
  if (process.env.AUTH_TEST_MYSQL_HOST !== 'mysql-auth') throw new Error('Disposable MySQL required');
  prefix = `stage3-${randomUUID()}`;
  database = await mysql.createConnection({
    host: 'mysql-auth', port: 3306, database: 'ensenada_auth_test',
    user: 'auth_setup', password: 'synthetic-setup-only-1234',
  });
  await database.execute('DELETE FROM auth_login_windows');
  admin = await fixtureUser('admin', 'Admin sintético de prueba', 'SUPER_ADMIN');
  await restrictNetwork(context);
});

test.afterEach(async () => {
  if (!database) return;
  const [rows] = await database.execute('SELECT id FROM usuarios WHERE email LIKE ?', [`${prefix}-%@example.test`]);
  for (const { id } of rows) {
    await database.execute('DELETE FROM auth_sessions WHERE usuario_id = ?', [id]);
    await database.execute('DELETE FROM usuario_roles WHERE usuario_id = ?', [id]);
    await database.execute('DELETE FROM usuarios WHERE id = ?', [id]);
  }
  await database.end();
  database = null;
});

function responseFor(page, method, path) {
  return page.waitForResponse(response => new URL(response.url()).pathname === path && response.request().method() === method);
}

async function login(page, email, password = PASSWORD, status = 200) {
  await page.goto('/admin/login');
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  const pending = responseFor(page, 'POST', '/api/auth/login');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  expect((await pending).status()).toBe(status);
  if (status === 200) await expect(page).toHaveURL(`${BASE}/admin`);
}

async function openUsers(page) {
  await login(page, admin.email);
  const pending = responseFor(page, 'GET', API);
  await page.getByRole('link', { name: 'Usuarios y roles', exact: true }).click();
  const response = await pending;
  expect(response.status()).toBe(200);
  for (const row of await response.json()) expect(Object.keys(row).sort()).toEqual(CONTRACT);
  await expect(page.getByRole('heading', { name: 'Gestión de permisos' })).toBeVisible();
}

function card(page, name) {
  return page.locator('article').filter({ has: page.getByRole('heading', { name, exact: true }) });
}

async function submitUser(page, name, email, role, status = 201) {
  await page.getByLabel('Nombre', { exact: true }).fill(name);
  await page.getByLabel('Email', { exact: true }).fill(email);
  await page.getByLabel('Contraseña inicial').fill(PASSWORD);
  await page.getByLabel('Rol operativo').selectOption(role);
  const pending = responseFor(page, 'POST', API);
  await page.getByRole('button', { name: 'Crear usuario' }).click();
  const response = await pending;
  expect(response.status()).toBe(status);
  const row = await response.json();
  if (status === 201) {
    expect(Object.keys(row).sort()).toEqual(CONTRACT);
    expect(row).toMatchObject({ nombre: name, email: email.toLowerCase(), roles: [role], activo: true });
    await expect(card(page, name)).toBeVisible();
  }
  return row;
}

async function operatorContext(browser) {
  const context = await browser.newContext({ baseURL: BASE });
  await restrictNetwork(context);
  return context;
}

async function requestFromPage(page, method, path, body) {
  return page.evaluate(async ({ method, path, body }) => (await fetch(path, {
    method, credentials: 'include', ...(body === undefined ? {} : {
      headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body),
    }),
  })).status, { method, path, body });
}

async function expectOldSessionRejected(page, context, oldCookies) {
  const pending = responseFor(page, 'GET', '/api/auth/me');
  await page.getByRole('link', { name: 'Mi cuenta', exact: true }).click();
  expect((await pending).status()).toBe(401);
  await expect(page).toHaveURL(`${BASE}/admin/login`);
  await context.addCookies(oldCookies);
  expect(await requestFromPage(page, 'GET', '/api/auth/me')).toBe(401);
}

test('Angular creates both roles, updates roles and reactivates without restoring the old session', async ({ page, browser, context }) => {
  await openUsers(page);
  const cookie = (await context.cookies()).find(item => item.name === 'WEB_ENSENADA_SESSION');
  expect(cookie).toMatchObject({ secure: true, httpOnly: true, sameSite: 'Strict', path: '/api' });
  const prensa = await submitUser(page, 'Operador de Prensa', address('prensa').toUpperCase(), 'PRENSA');
  await submitUser(page, 'Operadora de Hacienda', address('hacienda'), 'HACIENDA');
  const row = card(page, prensa.nombre);
  const role = responseFor(page, 'PUT', `${API}/${prensa.id}/roles`);
  await row.getByRole('combobox').selectOption('HACIENDA');
  expect(await (await role).json()).toEqual({ ...prensa, roles: ['HACIENDA'] });
  await expect(row.getByText('Activo · HACIENDA', { exact: true })).toBeVisible();

  const operator = await operatorContext(browser);
  try {
    const operatorPage = await operator.newPage();
    await login(operatorPage, prensa.email);
    const oldCookies = await operator.cookies();
    let pending = responseFor(page, 'PUT', `${API}/${prensa.id}/activo`);
    await row.getByRole('button', { name: 'Desactivar', exact: true }).click();
    const disabled = await pending;
    expect(disabled.status()).toBe(200);
    expect(await disabled.json()).toEqual({ ...prensa, roles: ['HACIENDA'], activo: false });
    await expect(row.getByText('Inactivo · HACIENDA', { exact: true })).toBeVisible();
    pending = responseFor(page, 'PUT', `${API}/${prensa.id}/activo`);
    await row.getByRole('button', { name: 'Activar', exact: true }).click();
    expect((await pending).status()).toBe(200);
    await expect(row.getByText('Activo · HACIENDA', { exact: true })).toBeVisible();
    await expectOldSessionRejected(operatorPage, operator, oldCookies);
    await login(operatorPage, prensa.email);
  } finally {
    await operator.close();
  }
});

test('Angular resets the password and the operator must log in with the new password', async ({ page, browser }) => {
  const user = await fixtureUser('reset', 'Cuenta a restablecer', 'PRENSA');
  await openUsers(page);
  const operator = await operatorContext(browser);
  try {
    const operatorPage = await operator.newPage();
    await login(operatorPage, user.email);
    const oldCookies = await operator.cookies();
    const row = card(page, 'Cuenta a restablecer');
    await row.getByLabel('Contraseña temporal').fill(RESET_PASSWORD);
    const pending = responseFor(page, 'PUT', `${API}/${user.id}/password`);
    await row.getByRole('button', { name: 'Restablecer contraseña' }).click();
    const response = await pending;
    expect(response.status()).toBe(204);
    expect(await response.text()).toBe('');
    await expect(page.getByText('Contraseña de Cuenta a restablecer actualizada. Las sesiones anteriores quedaron revocadas.')).toBeVisible();
    await expectOldSessionRejected(operatorPage, operator, oldCookies);
    await login(operatorPage, user.email, PASSWORD, 401);
    await login(operatorPage, user.email, RESET_PASSWORD);
    expect(await requestFromPage(page, 'GET', API)).toBe(200);
  } finally {
    await operator.close();
  }
});

test('Angular handles a case-insensitive duplicate with its existing error message', async ({ page }) => {
  const user = await fixtureUser('duplicate', 'Cuenta existente', 'PRENSA');
  await openUsers(page);
  await submitUser(page, 'Cuenta duplicada', user.email.toUpperCase(), 'HACIENDA', 409);
  await expect(page.getByText('No se pudo crear el usuario.', { exact: true })).toBeVisible();
  await expect(card(page, 'Cuenta existente')).toBeVisible();
  await expect(card(page, 'Cuenta duplicada')).toHaveCount(0);
});

for (const role of ['PRENSA', 'HACIENDA']) {
  test(`Laravel refuses ${role} even when the browser changes its local roles`, async ({ page }) => {
    const operator = await fixtureUser('operator', 'Operador sin permisos administrativos', role);
    await login(page, operator.email);
    await expect(page.getByRole('link', { name: 'Usuarios y roles', exact: true })).toHaveCount(0);
    await page.evaluate(() => {
      const user = JSON.parse(sessionStorage.getItem('admin-user'));
      sessionStorage.setItem('admin-user', JSON.stringify({ ...user, rol: 'SUPER_ADMIN', roles: ['SUPER_ADMIN'] }));
    });
    const pending = responseFor(page, 'GET', API);
    await page.goto('/admin/usuarios');
    expect((await pending).status()).toBe(403);
    await expect(page.getByText('No se pudieron cargar los usuarios.', { exact: true })).toBeVisible();
    expect(await requestFromPage(page, 'PUT', `${API}/${operator.id}/roles`, { roles: ['SUPER_ADMIN'] })).toBe(403);
  });
}

test('Angular hides controls for protected accounts and direct writes also fail', async ({ page }) => {
  const legacy = await fixtureUser('legacy', 'Admin histórico protegido', null, 'ROLE_ADMIN');
  await openUsers(page);
  for (const [name, id] of [['Admin sintético de prueba', admin.id], ['Admin histórico protegido', legacy.id]]) {
    const row = card(page, name);
    await expect(row.getByText('Activo · SUPER_ADMIN', { exact: true })).toBeVisible();
    await expect(row.getByRole('button')).toHaveCount(0);
    await expect(row.getByRole('combobox')).toHaveCount(0);
    for (const [operation, payload] of [['roles', { roles: ['HACIENDA'] }], ['activo', { activo: false }], ['password', { password: RESET_PASSWORD }]]) {
      expect(await requestFromPage(page, 'PUT', `${API}/${id}/${operation}`, payload)).toBe(403);
    }
  }
  expect(await requestFromPage(page, 'POST', API, {
    nombre: 'Intento de escalamiento', email: address('escalate'), password: PASSWORD, roles: ['SUPER_ADMIN'], activo: true,
  })).toBe(400);
});
