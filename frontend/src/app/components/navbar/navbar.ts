import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

type NavLink = {
  texto: string;
  ruta: string;
  exacta?: boolean;
  externo?: boolean;
  hijos?: NavLink[];
};

@Component({
  selector: 'app-navbar',
  imports: [RouterLink, RouterLinkActive],
  templateUrl: './navbar.html',
  styleUrl: './navbar.css',
})
export class Navbar {
  menuAbierto = false;

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
      texto: 'La ciudad',
      ruta: '/la-ciudad/historia',
      hijos: [
        { texto: 'Historia', ruta: '/la-ciudad/historia' },
        { texto: 'Teléfonos útiles', ruta: '/la-ciudad/telefonos-utiles' },
      ],
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
      ruta: 'https://boletinoficial.ensenada.gov.ar/index.php',
      externo: true,
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

  irArriba(): void {
    this.menuAbierto = false;
    setTimeout(() => window.scrollTo({ top: 0, left: 0, behavior: 'auto' }));
  }

  alternarMenu(): void {
    this.menuAbierto = !this.menuAbierto;
  }

  navegarDesdeMenu(event: MouseEvent): void {
    const enlace = event.currentTarget as HTMLElement | null;
    enlace?.closest('details')?.removeAttribute('open');
    this.irArriba();
  }
}
