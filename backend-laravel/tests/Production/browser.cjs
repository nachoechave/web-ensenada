const {chromium} = require('/tests/node_modules/playwright');
const fs = require('node:fs');
(async () => {
  const browser = await chromium.launch({args: ['--no-sandbox', '--host-resolver-rules=MAP www.ensenada.gov.ar 127.0.0.1']});
  const context = await browser.newContext({ignoreHTTPSErrors: true, viewport: {width: 1366, height: 900}});
  const page = await context.newPage();
  const errors=[]; page.on('pageerror', e=>errors.push(e.message));
  const origin='https://www.ensenada.gov.ar';
  page.on('dialog', dialog=>dialog.accept());
  await page.goto(origin+'/admin/login');
  await page.locator('input[name=email]').fill('admin@example.test');
  await page.locator('input[name=password]').fill('Synthetic-Production-Test-123!');
  await Promise.all([page.waitForResponse(r=>r.url().endsWith('/api/auth/login')&&r.status()===200),page.getByRole('button',{name:'Ingresar',exact:true}).click()]);
  await page.waitForURL(origin+'/admin');
  let checks=1;
  for(const path of ['/admin/usuarios','/admin/noticias','/admin/hacienda','/admin/proveedores','/admin/sitio']) {
    await Promise.all([page.waitForResponse(r=>r.url().includes('/api/admin/')&&r.request().method()==='GET'&&r.status()===200),page.goto(origin+path)]);
    checks++;
  }
  await page.goto(origin+'/admin/noticias/nueva');
  await page.locator('[name=titulo]').fill('Crónica sintética desde Chromium');
  await page.locator('[name=bajada]').fill('Bajada de aceptación local');
  await page.locator('[name=contenido]').fill('Contenido sintético de integración del formulario real.');
  await page.locator('[name=estado]').selectOption('PUBLICADA');
  await page.locator('input[type=file]').setInputFiles(['/results/original-4000.jpg','/results/original.png','/results/original.webp']);
  await page.locator('.admin-gallery-item').nth(2).waitFor();
  await page.waitForFunction(()=>Array.from(document.querySelectorAll('.admin-gallery img')).every(i=>i.complete&&i.naturalWidth>0));
  const sizes=await page.locator('.admin-gallery img').evaluateAll(imgs=>imgs.map(i=>[i.naturalWidth,i.naturalHeight]));
  if(sizes.some(([w,h])=>Math.max(w,h)>1920))throw Error('Imagen sin optimizar');
  checks+=4;
  const created=page.waitForResponse(r=>r.url().endsWith('/api/admin/noticias')&&r.request().method()==='POST'&&r.status()===201);
  await page.getByRole('button',{name:'Crear noticia',exact:true}).click();
  const news=await (await created).json();
  await page.waitForURL(origin+'/admin/noticias');checks++;
  for(const width of [1920,1366,390]) {
    await page.setViewportSize({width,height:900});
    await page.goto(origin+'/noticias/'+news.slug);
    await page.getByRole('heading',{name:'Crónica sintética desde Chromium'}).waitFor();
    await page.locator('.news-detail img, .noticia-detalle img, main img').first().waitFor().catch(()=>{});
    await page.screenshot({path:'/results/production-'+width+'.png',fullPage:true});checks++;
  }
  await page.goto(origin+'/admin/sitio');
  const hero=page.locator('details').filter({has:page.locator('summary').filter({hasText:/Hero|Carrusel|portada principal/i})}).first();
  await hero.locator('summary').click();
  const uploaded=page.waitForResponse(r=>r.url().endsWith('/api/admin/archivos/sitio')&&r.status()===200);
  await hero.locator('input[type=file]').setInputFiles('/results/original.webp');await uploaded;checks++;
  await Promise.all([page.waitForResponse(r=>r.url().endsWith('/api/admin/site-content')&&r.request().method()==='PUT'&&r.status()===200),page.getByRole('button',{name:'Guardar y publicar'}).first().click()]);checks++;
  await page.goto(origin+'/');await page.getByRole('heading',{name:/Portada local de producción/}).waitFor();checks++;
  if(errors.length)throw Error('Errores de JavaScript: '+errors.join('; '));checks++;
  fs.writeFileSync('/results/browser-result.json',JSON.stringify({checks,errors,newsId:news.id,slug:news.slug,sizes},null,2));
  console.log('OK '+checks+' comprobaciones Chromium HTTPS, formularios y tamaños responsive');
  await browser.close();
})().catch(e=>{console.error(e);process.exit(1);});
