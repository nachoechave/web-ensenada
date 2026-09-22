import { Routes } from '@angular/router';
import { ServicioExterno } from './pages/servicio-externo/servicio-externo';

import { adminGuard } from './core/admin.guard';
import { roleGuard } from './core/role.guard';
import { AdminDashboard } from './pages/admin/admin-dashboard/admin-dashboard';
import { AdminLayout } from './pages/admin/admin-layout/admin-layout';
import { AdminLogin } from './pages/admin/admin-login/admin-login';
import { AdminMiCuenta } from './pages/admin/admin-mi-cuenta/admin-mi-cuenta';
import { AdminNoticiaForm } from './pages/admin/admin-noticia-form/admin-noticia-form';
import { AdminNoticias } from './pages/admin/admin-noticias/admin-noticias';
import { AdminUsuarios } from './pages/admin/admin-usuarios/admin-usuarios';
import { Home } from './pages/home/home';
import { NoticiaDetalle } from './pages/noticia-detalle/noticia-detalle';
import { Noticias } from './pages/noticias/noticias';

export const routes: Routes = [
  {
    path: 'hacienda',
    component: ServicioExterno,
    data: { servicio: 'hacienda', nombre: 'Hacienda' },
    title: 'Hacienda | Municipalidad de Ensenada',
  },
  {
    path: 'registro-proveedores',
    component: ServicioExterno,
    data: { servicio: 'proveedores', nombre: 'Registro Municipal de Proveedores' },
    title: 'Proveedores | Municipalidad de Ensenada',
  },
  {
    path: '',
    component: Home,
    title: 'Municipalidad de Ensenada',
  },
  {
    path: 'noticias',
    component: Noticias,
    title: 'Noticias | Municipalidad de Ensenada',
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
    canActivateChild: [adminGuard],
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
