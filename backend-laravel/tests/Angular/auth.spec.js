const { test, expect } = require('@playwright/test');
const mysql = require('mysql2/promise');
const crypto = require('node:crypto');
const fixture = require('./java-bcrypt.json');

const BASE = 'https://localhost:18443';
const PASSWORD = 'Synthetic-password-1234';
let database;
let email;
let ids;
let wire;

test.beforeEach(async ({ context, page }) => {
  if (process.env.AUTH_TEST_MYSQL_HOST !== 'mysql-auth') throw new Error('Only the disposable MySQL service is allowed');
  database = await mysql.createConnection({
    host: 'mysql-auth', port: 3306, database: 'ensenada_auth_test',
    user: 'auth_setup', password: 'synthetic-setup-only-1234',
  });
  await database.execute('DELETE FROM auth_login_windows');
  ids = [];
  email = await createUser();
  wire = new Map();
  const cdp = await context.newCDPSession(page);
  await cdp.send('Network.enable');
  const entry = id => {
    if (!wire.has(id)) wire.set(id, {});
    return wire.get(id);
  };
  cdp.on('Network.requestWillBeSent', event => {
    Object.assign(entry(event.requestId), { url: event.request.url, method: event.request.method });
  });
  cdp.on('Network.requestWillBeSentExtraInfo', event => {
    const headers = Object.fromEntries(Object.entries(event.headers).map(([key, value]) => [key.toLowerCase(), value]));
    // Store only security metadata; do not put JWT/cookie values into reports.
    entry(event.requestId).headers = {
      origin: headers.origin,
      'sec-fetch-site': headers['sec-fetch-site'],
      authorization: headers.authorization ? 'present' : undefined,
      hasSessionCookie: (headers.cookie ?? '').includes('WEB_ENSENADA_SESSION='),
    };
  });
  cdp.on('Network.responseReceivedExtraInfo', event => {
    entry(event.requestId).status = event.statusCode;
  });
  // This is an outbound-network guard, not a mock of API responses.
  await context.route('**/*', route => {
    const host = new URL(route.request().url()).hostname;
    return host === 'localhost' ? route.continue() : route.abort('blockedbyclient');
  });
});

async function wireRequest(path, method, origin = BASE) {
  const find = () => [...wire.values()].reverse().find(record =>
    record.url === BASE+path && record.method === method && record.headers?.origin === origin);
  await expect.poll(() => find()?.headers?.['sec-fetch-site']).toBeTruthy();
  return find();
}

test.afterEach(async () => {
  if (!database) return;
  for (const id of ids ?? []) {
    for (const table of ['auth_sessions', 'usuario_roles']) {
      await database.execute('DELETE FROM '+table+' WHERE usuario_id = ?', [id]);
    }
    await database.execute('DELETE FROM usuarios WHERE id = ?', [id]);
  }
  await database.end();
  database = null;
});

async function createUser(role = 'PRENSA') {
  const address = 'angular-'+crypto.randomUUID()+'@example.test';
  const [result] = await database.execute(
    'INSERT INTO usuarios(nombre,email,password,rol,activo,token_version) VALUES(?,?,?,?,1,0)',
    ['Operador Angular sintético', address, fixture.vectors[0].hash, role],
  );
  ids.push(result.insertId);
  await database.execute('INSERT INTO usuario_roles(usuario_id,rol) VALUES(?,?)', [result.insertId, role]);
  return address;
}

function responseFor(page, path, method) {
  return page.waitForResponse(response =>
    new URL(response.url()).pathname === path && response.request().method() === method);
}

async function login(page, password = PASSWORD, address = email) {
  await page.goto('/admin/login');
  await page.getByLabel('Email', { exact: true }).fill(address);
  await page.getByLabel('Contraseña', { exact: true }).fill(password);
  const pending = responseFor(page, '/api/auth/login', 'POST');
  await page.getByRole('button', { name: 'Ingresar', exact: true }).click();
  return pending;
}

async function account(page) {
  const pending = responseFor(page, '/api/auth/me', 'GET');
  await page.getByRole('link', { name: 'Mi cuenta', exact: true }).click();
  expect((await pending).status()).toBe(200);
  await expect(page.getByLabel('Nombre', { exact: true })).toHaveValue('Operador Angular sintético');
}

test('Angular logs in directly with native Fetch Metadata and HttpOnly cookie', async ({ page, context }) => {
  const authCalls = [];
  const browserErrors = [];
  page.on('pageerror', error => browserErrors.push(error.message));
  page.on('console', message => {
    if (message.type() === 'error' && message.location().url.startsWith(BASE)) browserErrors.push(message.text());
  });
  page.on('response', response => {
    if (response.url().startsWith(BASE+'/api/') && response.status() >= 500) browserErrors.push(`${response.status()} ${response.url()}`);
  });
  page.on('request', request => {
    if (new URL(request.url()).pathname.startsWith('/api/auth/')) authCalls.push(request);
  });

  const response = await login(page);

  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(Object.keys(data).sort()).toEqual(['email', 'nombre', 'rol', 'roles', 'token']);
  expect(data.roles).toEqual(['PRENSA']);
  const headers = (await wireRequest('/api/auth/login', 'POST')).headers;
  expect(headers['sec-fetch-site']).toBe('same-origin');
  expect(headers.origin).toBe(BASE);
  expect(headers.authorization).toBeUndefined();
  expect(authCalls[0].method()).toBe('POST');
  expect(authCalls[0].url()).toBe(BASE+'/api/auth/login');
  const cookie = (await context.cookies()).find(cookie => cookie.name === 'WEB_ENSENADA_SESSION');
  expect(cookie).toMatchObject({ secure: true, httpOnly: true, sameSite: 'Strict', path: '/api' });
  await expect(page).toHaveURL(BASE+'/admin');
  expect(await page.evaluate(() => ({
    localToken: localStorage.getItem('admin-token'),
    sessionToken: sessionStorage.getItem('admin-token'),
    storedToken: JSON.parse(sessionStorage.getItem('admin-user')).token ?? null,
    visibleCookie: document.cookie.includes('WEB_ENSENADA_SESSION'),
  }))).toEqual({ localToken: null, sessionToken: null, storedToken: null, visibleCookie: false });
  await account(page);
  expect(browserErrors).toEqual([]);
});

for (const role of ['PRENSA', 'HACIENDA', 'SUPER_ADMIN']) {
  test('existing Angular menu reflects '+role, async ({ page }) => {
    const address = await createUser(role);

    expect((await login(page, PASSWORD, address)).status()).toBe(200);

    await expect(page.getByRole('link', { name: 'Mi cuenta', exact: true })).toBeVisible();
    const news = page.getByRole('link', { name: 'Noticias', exact: true });
    const treasury = page.getByRole('link', { name: 'Hacienda', exact: true });
    const users = page.getByRole('link', { name: 'Usuarios y roles', exact: true });
    if (role !== 'HACIENDA') await expect(news).toBeVisible(); else await expect(news).toHaveCount(0);
    if (role !== 'PRENSA') await expect(treasury).toBeVisible(); else await expect(treasury).toHaveCount(0);
    if (role === 'SUPER_ADMIN') await expect(users).toBeVisible(); else await expect(users).toHaveCount(0);
  });
}

test('Angular handles wrong credentials and inactive accounts with the existing messages', async ({ page, context }) => {
  expect((await login(page, 'Wrong-password-1234')).status()).toBe(401);
  await expect(page.getByText('Email o contraseña incorrectos.', { exact: true })).toBeVisible();
  await database.execute('UPDATE usuarios SET activo=0 WHERE email=?', [email]);

  expect((await login(page)).status()).toBe(403);

  await expect(page.getByText('Email o contraseña incorrectos.', { exact: true })).toBeVisible();
  expect((await context.cookies()).find(cookie => cookie.name === 'WEB_ENSENADA_SESSION')).toBeUndefined();
});

test('Angular saves profile, consumes the JSON contract and rotates the cookie', async ({ page, context }) => {
  expect((await login(page)).status()).toBe(200);
  await account(page);
  const oldCookie = (await context.cookies()).find(cookie => cookie.name === 'WEB_ENSENADA_SESSION').value;
  const updatedEmail = 'updated-'+email;
  await page.getByLabel('Nombre', { exact: true }).fill('Operadora Angular validada');
  await page.getByLabel('Email', { exact: true }).fill(updatedEmail.toUpperCase());
  const pending = responseFor(page, '/api/auth/me', 'PUT');

  await page.getByRole('button', { name: 'Guardar datos', exact: true }).click();

  const response = await pending;
  expect(response.status()).toBe(200);
  const data = await response.json();
  expect(Object.keys(data).sort()).toEqual(['email', 'nombre', 'rol', 'roles', 'token']);
  expect(data.email).toBe(updatedEmail);
  expect((await wireRequest('/api/auth/me', 'PUT')).headers['sec-fetch-site']).toBe('same-origin');
  await expect(page.getByText('Tus datos se actualizaron correctamente.', { exact: true })).toBeVisible();
  const cookie = (await context.cookies()).find(cookie => cookie.name === 'WEB_ENSENADA_SESSION');
  expect(cookie.value === oldCookie).toBe(false);
  expect(await page.evaluate(() => JSON.parse(sessionStorage.getItem('admin-user')).email)).toBe(updatedEmail);
});

test('Angular displays the profile conflict from HTTP 409 without losing the session', async ({ page }) => {
  const occupied = await createUser();
  expect((await login(page)).status()).toBe(200);
  await account(page);
  await page.getByLabel('Email', { exact: true }).fill(occupied);
  const pending = responseFor(page, '/api/auth/me', 'PUT');

  await page.getByRole('button', { name: 'Guardar datos', exact: true }).click();

  expect((await pending).status()).toBe(409);
  await expect(page.getByText('Ya existe un usuario con ese email', { exact: true })).toBeVisible();
  expect(await page.evaluate(async () => (await fetch('/api/auth/me', { credentials: 'include' })).status)).toBe(200);
});

test('Angular logout sends its real null payload and clears both cookie and local state', async ({ page, context }) => {
  expect((await login(page)).status()).toBe(200);
  const pending = responseFor(page, '/api/auth/logout', 'POST');

  await page.getByRole('button', { name: 'Cerrar sesión', exact: true }).click();

  const response = await pending;
  expect(response.status()).toBe(204);
  expect((await wireRequest('/api/auth/logout', 'POST')).headers['sec-fetch-site']).toBe('same-origin');
  await expect(page).toHaveURL(BASE+'/admin/login');
  expect((await context.cookies()).find(cookie => cookie.name === 'WEB_ENSENADA_SESSION')).toBeUndefined();
  expect(await page.evaluate(() => sessionStorage.getItem('admin-user'))).toBeNull();
});

test('Angular handles current-password 400 and successful password-change 204', async ({ page, context }) => {
  expect((await login(page)).status()).toBe(200);
  await account(page);
  await page.getByLabel('Contraseña actual', { exact: true }).fill('Wrong-password-1234');
  await page.getByLabel('Nueva contraseña', { exact: true }).fill('Angular-new-password-5678');
  await page.getByLabel('Repetir nueva contraseña', { exact: true }).fill('Angular-new-password-5678');
  let pending = responseFor(page, '/api/auth/me/password', 'PUT');
  await page.getByRole('button', { name: 'Cambiar contraseña', exact: true }).click();
  expect((await pending).status()).toBe(400);
  await expect(page.getByText('La contraseña actual no es correcta', { exact: true })).toBeVisible();
  await page.getByLabel('Contraseña actual', { exact: true }).fill(PASSWORD);
  pending = responseFor(page, '/api/auth/me/password', 'PUT');

  await page.getByRole('button', { name: 'Cambiar contraseña', exact: true }).click();

  expect((await pending).status()).toBe(204);
  await expect(page).toHaveURL(BASE+'/admin/login');
  expect((await context.cookies()).find(cookie => cookie.name === 'WEB_ENSENADA_SESSION')).toBeUndefined();
  expect((await login(page, 'Angular-new-password-5678')).status()).toBe(200);
});

test('Angular handles rate-limit 429 after ten real failed logins', async ({ page }) => {
  for (let i = 0; i < 10; i++) {
    expect((await login(page, 'Wrong-password-1234')).status()).toBe(401);
  }

  const response = await login(page, 'Wrong-password-1234');

  expect(response.status()).toBe(429);
  expect(Number(response.headers()['retry-after'])).toBeGreaterThan(0);
  await expect(page.getByText('Demasiados intentos. Esperá unos minutos antes de volver a iniciar sesión.', { exact: true })).toBeVisible();
});

test('a browser request from another HTTPS origin cannot logout the user even with cookies', async ({ page }) => {
  expect((await login(page)).status()).toBe(200);
  await expect(page).toHaveURL(BASE+'/admin');
  await page.goto('https://localhost:19443');
  await page.evaluate(async () => {
    try { await fetch('https://localhost:18443/api/auth/logout', { method: 'POST', credentials: 'include' }); } catch {}
  });

  const request = await wireRequest('/api/auth/logout', 'POST', 'https://localhost:19443');
  await expect.poll(() => request.status).toBe(403);
  const headers = request.headers;
  expect(headers['sec-fetch-site']).toBe('same-site');
  expect(headers.origin).toBe('https://localhost:19443');
  expect(headers.hasSessionCookie).toBe(true);
  await page.goto(BASE+'/admin/mi-cuenta');
  await expect(page.getByLabel('Nombre', { exact: true })).toHaveValue('Operador Angular sintético');
});

test('the Angular interceptor reacts to MySQL revocation with 401 and redirects to login', async ({ page, context }) => {
  const dashboardNews = responseFor(page, '/api/admin/noticias', 'GET');
  expect((await login(page)).status()).toBe(200);
  await expect(page).toHaveURL(BASE+'/admin');
  expect((await dashboardNews).status()).toBe(200);
  const oldCookies = await context.cookies();
  await database.execute('UPDATE usuarios SET activo=0 WHERE email=?', [email]);
  await database.execute('UPDATE usuarios SET activo=1 WHERE email=?', [email]);
  const pending = responseFor(page, '/api/auth/me', 'GET');

  await page.getByRole('link', { name: 'Mi cuenta', exact: true }).click();

  expect((await pending).status()).toBe(401);
  await expect(page).toHaveURL(BASE+'/admin/login');
  expect(await page.evaluate(() => sessionStorage.getItem('admin-user'))).toBeNull();
  await context.addCookies(oldCookies);
  expect(await page.evaluate(async () => (await fetch('/api/auth/me', { credentials: 'include' })).status)).toBe(401);
});
