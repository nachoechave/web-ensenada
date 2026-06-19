import { Routes } from '@angular/router';

import { Home } from './pages/home/home';
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
];