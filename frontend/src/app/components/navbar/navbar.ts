import { externalLinks } from '../../config/external-links';
import { Component } from '@angular/core';
import { RouterLink, RouterLinkActive } from '@angular/router';

type NavLink = {
  texto: string;
  ruta: string;
  exacta?: boolean;
  externo?: boolean;
  fragmento?: string;
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
    { texto: 'Inicio', ruta: '/', exacta: true },
    { texto: 'Intendente', ruta: '/', fragmento: 'intendente' },
    { texto: 'Servicios', ruta: '/', fragmento: 'servicios' },
    { texto: 'Noticias', ruta: '/noticias' },
    { texto: 'Hacienda', ruta: '/hacienda' },
    { texto: 'Boletín Oficial', ruta: externalLinks.boletinOficial, externo: true },
  ];

  irArriba(): void {
    this.menuAbierto = false;
  }

  alternarMenu(): void {
    this.menuAbierto = !this.menuAbierto;
  }
}
