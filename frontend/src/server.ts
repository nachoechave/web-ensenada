import { AngularNodeAppEngine, createNodeRequestHandler, isMainModule, writeResponseToNodeResponse } from '@angular/ssr/node';
import express from 'express';
import http from 'node:http';
import https from 'node:https';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const serverDistFolder = dirname(fileURLToPath(import.meta.url));
const browserDistFolder = resolve(serverDistFolder, '../browser');

const app = express();
const angularApp = new AngularNodeAppEngine();

const backendUrl = process.env['BACKEND_URL'] ?? 'http://localhost:8080';

function proxyToBackend(req: express.Request, res: express.Response): void {
  let target: URL;

  try {
    target = new URL(req.originalUrl, backendUrl);
  } catch {
    res.status(500).json({ error: 'BACKEND_URL invalida' });
    return;
  }

  const transport = target.protocol === 'https:' ? https : http;
  const forwardedFor = req.headers['x-forwarded-for'];
  const remoteAddress = req.socket.remoteAddress;
  const xForwardedFor = [forwardedFor, remoteAddress].filter(Boolean).join(', ');

  const headers = {
    ...req.headers,
    host: req.headers.host ?? 'ensenada.comercioflex.com.ar',
    'x-forwarded-host': req.headers.host ?? 'ensenada.comercioflex.com.ar',
    'x-forwarded-proto': req.headers['x-forwarded-proto'] ?? req.protocol,
    ...(xForwardedFor ? { 'x-forwarded-for': xForwardedFor } : {}),
  };

  // Las imagenes publicas no requieren credenciales del panel administrativo.
  // Una cookie o token vencido no debe convertir su descarga en un 401.
  if (req.method === 'GET' && /^\/uploads\/(noticias|sitio)\//.test(req.originalUrl)) {
    delete headers.cookie;
    delete headers.authorization;
  }

  const proxyRequest = transport.request(
    target,
    {
      method: req.method,
      headers,
    },
    (proxyResponse) => {
      res.status(proxyResponse.statusCode ?? 502);

      for (const [name, value] of Object.entries(proxyResponse.headers)) {
        if (value !== undefined) {
          res.setHeader(name, value);
        }
      }

      proxyResponse.pipe(res);
    },
  );

  proxyRequest.on('error', (error) => {
    console.error(`Error proxying ${req.method} ${req.originalUrl} to backend:`, error);
    if (!res.headersSent) {
      res.status(502).json({ error: 'Backend no disponible' });
    } else {
      res.end();
    }
  });

  req.pipe(proxyRequest);
}

app.use(['/api', '/uploads'], proxyToBackend);

app.use(
  express.static(browserDistFolder, {
    maxAge: '1y',
    index: false,
    redirect: false,
  }),
);

app.use((req, res, next) => {
  angularApp
    .handle(req)
    .then((response) => {
      if (response) {
        writeResponseToNodeResponse(response, res);
      } else {
        next();
      }
    })
    .catch(next);
});

if (isMainModule(import.meta.url)) {
  const port = Number(process.env['PORT'] ?? 4000);
  app.listen(port, () => {
    console.log(`Angular SSR server listening on http://localhost:${port}`);
    console.log(`Proxying /api and /uploads to ${backendUrl}`);
  });
}

export const reqHandler = createNodeRequestHandler(app);
