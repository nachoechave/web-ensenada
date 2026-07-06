import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ContenidoSitioService } from '../../services/contenido-sitio.service';

@Component({
  selector: 'app-footer',
  imports: [RouterLink],
  templateUrl: './footer.html',
  styleUrl: './footer.css',
})
export class Footer {
  private readonly contenidoSitioService = inject(ContenidoSitioService);

  contenido = this.contenidoSitioService.obtenerContenido();

  constructor() {
    this.contenidoSitioService.obtenerContenidoDesdeApi().subscribe({
      next: (contenido) => {
        this.contenido = contenido;
        this.contenidoSitioService.guardarContenido(contenido);
      },
      error: () => {
        this.contenido = this.contenidoSitioService.obtenerContenido();
      },
    });
  }
}
