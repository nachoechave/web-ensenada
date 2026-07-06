import { Routes } from '@angular/router';

import { adminGuard } from './core/admin.guard';
import { roleGuard } from './core/role.guard';
import { Agenda } from './pages/agenda/agenda';
import { AdminBoletines } from './pages/admin/admin-boletines/admin-boletines';
import { AdminAgenda } from './pages/admin/admin-agenda/admin-agenda';
import { AdminContenido } from './pages/admin/admin-contenido/admin-contenido';
import { AdminDashboard } from './pages/admin/admin-dashboard/admin-dashboard';
import { AdminHacienda } from './pages/admin/admin-hacienda/admin-hacienda';
import { AdminLayout } from './pages/admin/admin-layout/admin-layout';
import { AdminLogin } from './pages/admin/admin-login/admin-login';
import { AdminMiCuenta } from './pages/admin/admin-mi-cuenta/admin-mi-cuenta';
import { AdminNoticiaForm } from './pages/admin/admin-noticia-form/admin-noticia-form';
import { AdminNoticias } from './pages/admin/admin-noticias/admin-noticias';
import { AdminUsuarios } from './pages/admin/admin-usuarios/admin-usuarios';
import { Areas } from './pages/areas/areas';
import { BoletinOficial } from './pages/boletin-oficial/boletin-oficial';
import { Contacto } from './pages/contacto/contacto';
import { Hacienda } from './pages/hacienda/hacienda';
import { Home } from './pages/home/home';
import { NoticiaDetalle } from './pages/noticia-detalle/noticia-detalle';
import { Noticias } from './pages/noticias/noticias';

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
    path: 'agenda',
    component: Agenda,
  },
  {
    path: 'areas',
    component: Areas,
  },
  {
    path: 'contacto',
    component: Contacto,
  },
  {
    path: 'boletin-oficial',
    component: BoletinOficial,
  },
  {
    path: 'hacienda',
    component: Hacienda,
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
        canActivate: [roleGuard],
        data: { roles: ['PRENSA'] },
      },
      {
        path: 'noticias/nueva',
        component: AdminNoticiaForm,
        canActivate: [roleGuard],
        data: { roles: ['PRENSA'] },
      },
      {
        path: 'noticias/editar/:id',
        component: AdminNoticiaForm,
        canActivate: [roleGuard],
        data: { roles: ['PRENSA'] },
      },
      {
        path: 'boletin-oficial',
        component: AdminBoletines,
        canActivate: [roleGuard],
        data: { roles: ['BOLETIN_OFICIAL'] },
      },
      {
        path: 'agenda',
        component: AdminAgenda,
        canActivate: [roleGuard],
        data: { roles: ['CONTENIDO'] },
      },
      {
        path: 'hacienda',
        component: AdminHacienda,
        canActivate: [roleGuard],
        data: { roles: ['HACIENDA'] },
      },
      {
        path: 'contenido',
        component: AdminContenido,
        canActivate: [roleGuard],
        data: { roles: ['CONTENIDO'] },
      },
      {
        path: 'usuarios',
        component: AdminUsuarios,
        canActivate: [roleGuard],
        data: { roles: ['SUPER_ADMIN'] },
      },
      {
        path: 'mi-cuenta',
        component: AdminMiCuenta,
      },
    ],
  },
  {
    path: '**',
    redirectTo: '',
  },
];
