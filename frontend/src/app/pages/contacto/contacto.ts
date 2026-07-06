import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { ContenidoSitio } from '../../models/contenido-sitio.model';
import { ContenidoSitioService } from '../../services/contenido-sitio.service';

@Component({
  selector: 'app-contacto',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './contacto.html',
  styleUrl: './contacto.css',
})
export class Contacto {
  private readonly contenidoService = inject(ContenidoSitioService);

  contenido: ContenidoSitio = this.contenidoService.obtenerContenidoInicial();

  constructor() {
    this.contenidoService.obtenerContenidoDesdeApi().subscribe({
      next: (contenido) => {
        this.contenido = contenido;
      },
      error: () => {
        this.contenido = this.contenidoService.obtenerContenidoInicial();
      },
    });
  }
}
