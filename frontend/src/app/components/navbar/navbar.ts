import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

type NavLink = {
  texto: string;
  ruta: string;
  exacta?: boolean;
};

@Component({
  selector: 'app-navbar',
  imports: [RouterLink, RouterLinkActive],
  templateUrl: './navbar.html',
  styleUrl: './navbar.css',
})
export class Navbar {
  links: NavLink[] = [
    {
      texto: 'Inicio',
      ruta: '/',
      exacta: true,
    },
    {
      texto: 'Áreas',
      ruta: '/areas',
    },
    {
      texto: 'Noticias',
      ruta: '/noticias',
    },
    {
      texto: 'Agenda',
      ruta: '/agenda',
    },
    {
      texto: 'Boletín Oficial',
      ruta: '/boletin-oficial',
    },
    {
      texto: 'Hacienda',
      ruta: '/hacienda',
    },
    {
      texto: 'Contacto',
      ruta: '/contacto',
    },
  ];

  irAlInicio(): void {
    setTimeout(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
  }
}
