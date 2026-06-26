import { Routes } from '@angular/router';

import { Home } from './pages/home/home';
import { Noticias } from './pages/noticias/noticias';
import { NoticiaDetalle } from './pages/noticia-detalle/noticia-detalle';

import { AdminLayout } from './pages/admin/admin-layout/admin-layout';
import { AdminDashboard } from './pages/admin/admin-dashboard/admin-dashboard';
import { AdminNoticias } from './pages/admin/admin-noticias/admin-noticias';
import { AdminNoticiaForm } from './pages/admin/admin-noticia-form/admin-noticia-form';
import { AdminLogin } from './pages/admin/admin-login/admin-login';

import { adminGuard } from './core/admin.guard';

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
    path: 'admin/login',
    component: AdminLogin,
  },
  {
    path: 'admin',
    component: AdminLayout,
    canActivate: [adminGuard],
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