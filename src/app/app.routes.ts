import { Routes } from '@angular/router';

import { Home } from './pages/home/home';
import { Noticias } from './pages/noticias/noticias';
import { NoticiaDetalle } from './pages/noticia-detalle/noticia-detalle';

import { AdminLayout } from './pages/admin/admin-layout/admin-layout';
import { AdminDashboard } from './pages/admin/admin-dashboard/admin-dashboard';
import { AdminNoticias } from './pages/admin/admin-noticias/admin-noticias';
import { AdminNoticiaForm } from './pages/admin/admin-noticia-form/admin-noticia-form';

export const routes: Routes = [
  {
    path: '',
    component: Home,
  },
  {
    path: 'noticias',
    component: Noticias,
  },
  {
    path: 'noticias/:id',
    component: NoticiaDetalle,
  },
  {
    path: 'admin',
    component: AdminLayout,
    children: [
      {
        path: '',
        component: AdminDashboard,
      },
      {
        path: 'noticias',
        component: AdminNoticias,
      },
      {
        path: 'noticias/nueva',
        component: AdminNoticiaForm,
      },
      {
        path: 'noticias/editar/:id',
        component: AdminNoticiaForm,
      },
    ],
  },
  {
    path: '**',
    redirectTo: '',
  },
];