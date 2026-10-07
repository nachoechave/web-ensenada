import { RenderMode, ServerRoute } from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  {
    path: 'noticias/:id',
    renderMode: RenderMode.Server,
  },
  {
    path: 'noticias',
    renderMode: RenderMode.Server,
  },
  {
    path: 'hacienda/:id',
    renderMode: RenderMode.Server,
  },
  {
    path: 'hacienda',
    renderMode: RenderMode.Server,
  },
  {
    path: 'areas',
    renderMode: RenderMode.Client,
  },
  {
    path: 'intendencia',
    renderMode: RenderMode.Server,
  },
  {
    path: 'proveedores',
    renderMode: RenderMode.Server,
  },
  {
    path: '',
    renderMode: RenderMode.Server,
  },
  {
    path: 'admin/**',
    renderMode: RenderMode.Client,
  },
  {
    path: '**',
    renderMode: RenderMode.Client,
  },
];
