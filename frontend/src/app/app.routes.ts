import { Routes } from '@angular/router';

import { Home } from './pages/home/home';
import { Noticias } from './pages/noticias/noticias';
import { NoticiaDetalle } from './pages/noticia-detalle/noticia-detalle';

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
];