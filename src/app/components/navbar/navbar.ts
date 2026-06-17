import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

type NavLink = {
  texto: string;
  ruta: string;
};

@Component({
  selector: 'app-navbar',
  imports: [RouterLink],
  templateUrl: './navbar.html',
  styleUrl: './navbar.css',
})
export class Navbar {
  links: NavLink[] = [
    {
      texto: 'Inicio',
      ruta: '/',
    },
    {
      texto: 'Municipio',
      ruta: '#',
    },
    {
      texto: 'Áreas',
      ruta: '#',
    },
    {
      texto: 'Trámites',
      ruta: '#',
    },
    {
      texto: 'Noticias',
      ruta: '/noticias',
    },
    {
      texto: 'Cultura',
      ruta: '#',
    },
    {
      texto: 'Deportes',
      ruta: '#',
    },
    {
      texto: 'Contacto',
      ruta: '#',
    },
  ];
}