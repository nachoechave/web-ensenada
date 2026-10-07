import { Hacienda } from './pages/hacienda/hacienda';
import { Areas } from './pages/areas/areas';
import { Intendencia } from './pages/intendencia/intendencia';
import { HaciendaDetalle } from './pages/hacienda-detalle/hacienda-detalle';
import { AdminHacienda } from './pages/admin/admin-hacienda/admin-hacienda';
import { AdminHaciendaForm } from './pages/admin/admin-hacienda-form/admin-hacienda-form';
import { Routes } from '@angular/router';

import { adminGuard } from './core/admin.guard';
import { roleGuard } from './core/role.guard';
import { AdminAuditoriaNoticias } from './pages/admin/admin-auditoria-noticias/admin-auditoria-noticias';
import { AdminDashboard } from './pages/admin/admin-dashboard/admin-dashboard';
import { AdminLayout } from './pages/admin/admin-layout/admin-layout';
import { AdminLogin } from './pages/admin/admin-login/admin-login';
import { AdminMiCuenta } from './pages/admin/admin-mi-cuenta/admin-mi-cuenta';
import { AdminNoticiaForm } from './pages/admin/admin-noticia-form/admin-noticia-form';
import { AdminNoticias } from './pages/admin/admin-noticias/admin-noticias';
import { AdminSitio } from './pages/admin/admin-sitio/admin-sitio';
import { AdminProveedores } from './pages/admin/admin-proveedores/admin-proveedores';
import { AdminUsuarios } from './pages/admin/admin-usuarios/admin-usuarios';
import { Home } from './pages/home/home';
import { NoticiaDetalle } from './pages/noticia-detalle/noticia-detalle';
import { Noticias } from './pages/noticias/noticias';
import { RegistroProveedores } from './pages/registro-proveedores/registro-proveedores';

export const routes: Routes = [
  { path: 'hacienda/:id', component: HaciendaDetalle },
  {
    path: 'hacienda',
    component: Hacienda,
    title: 'Hacienda | Municipalidad de Ensenada',
  },
  {
    path: 'areas',
    component: Areas,
    title: 'Secretarías y áreas | Municipalidad de Ensenada',
  },
  {
    path: 'intendencia',
    component: Intendencia,
    title: 'Intendencia | Municipalidad de Ensenada',
  },
  {
    path: 'proveedores',
    component: RegistroProveedores,
    title: 'Registro Municipal de Proveedores | Municipalidad de Ensenada',
  },
  {
    path: 'registro-proveedores',
    redirectTo: 'proveedores',
    pathMatch: 'full',
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
        path: 'hacienda',
        component: AdminHacienda,
        canActivate: [roleGuard],
        data: { roles: ['HACIENDA'] },
      },
      {
        path: 'hacienda/nueva',
        component: AdminHaciendaForm,
        canActivate: [roleGuard],
        data: { roles: ['HACIENDA'] },
      },
      {
        path: 'hacienda/editar/:id',
        component: AdminHaciendaForm,
        canActivate: [roleGuard],
        data: { roles: ['HACIENDA'] },
      },
      {
        path: '',
        component: AdminDashboard,
      },
      {
        path: 'sitio',
        component: AdminSitio,
        canActivate: [roleGuard],
        data: { roles: ['SUPER_ADMIN'] },
      },
      {
        path: 'proveedores',
        component: AdminProveedores,
        canActivate: [roleGuard],
        data: { roles: ['SUPER_ADMIN', 'HACIENDA'] },
      },
      {
        path: 'noticias',
        component: AdminNoticias,
        canActivate: [roleGuard],
        data: { roles: ['PRENSA'] },
      },
      {
        path: 'noticias/auditoria',
        component: AdminAuditoriaNoticias,
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
