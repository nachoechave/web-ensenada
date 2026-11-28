const { defineConfig } = require('@playwright/test');
const output = process.env.ENSENADA_BROWSER_OUTPUT ?? '/results';

module.exports = defineConfig({
  testDir: '.',
  testMatch: ['auth.spec.js', 'usuarios-admin.spec.js', 'noticias.spec.js', 'noticias-admin.spec.js', 'sitio.spec.js', 'hacienda.spec.js', 'proveedores.spec.js'],
  timeout: 30000,
  workers: 1,
  retries: 0,
  reporter: [['list'], ['json', { outputFile: `${output}/playwright.json` }]],
  outputDir: `${output}/artifacts`,
  use: {
    baseURL: 'https://localhost:18443',
    browserName: 'chromium',
    headless: true,
    ignoreHTTPSErrors: false,
    viewport: { width: 1440, height: 1000 },
    trace: 'off',
    screenshot: 'only-on-failure',
  },
});
