import { Routes } from '@angular/router';

import { adminGuard } from './core/admin.guard';
import { roleGuard } from './core/role.guard';
import { Agenda } from './pages/agenda/agenda';
import { AdminAgenda } from './pages/admin/admin-agenda/admin-agenda';
import { AdminAreas } from './pages/admin/admin-areas/admin-areas';
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
import { Contacto } from './pages/contacto/contacto';
import { Hacienda } from './pages/hacienda/hacienda';
import { Home } from './pages/home/home';
import { Historia } from './pages/historia/historia';
import { NoticiaDetalle } from './pages/noticia-detalle/noticia-detalle';
import { Noticias } from './pages/noticias/noticias';
import { RegistroProveedores } from './pages/registro-proveedores/registro-proveedores';
import { TelefonosUtiles } from './pages/telefonos-utiles/telefonos-utiles';

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
    path: 'la-ciudad/historia',
    component: Historia,
  },
  {
    path: 'la-ciudad/telefonos-utiles',
    component: TelefonosUtiles,
  },
  {
    path: 'contacto',
    component: Contacto,
  },
  {
    path: 'hacienda',
    component: Hacienda,
  },
  {
    path: 'registro-proveedores',
    component: RegistroProveedores,
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
        path: 'agenda',
        component: AdminAgenda,
        canActivate: [roleGuard],
        data: { roles: ['CONTENIDO'] },
      },
      {
        path: 'areas',
        component: AdminAreas,
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
