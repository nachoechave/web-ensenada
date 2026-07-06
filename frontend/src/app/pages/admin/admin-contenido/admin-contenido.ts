import { Component, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { ContenidoSitio } from '../../../models/contenido-sitio.model';
import { ContenidoSitioService } from '../../../services/contenido-sitio.service';

@Component({
  selector: 'app-admin-contenido',
  imports: [FormsModule],
  templateUrl: './admin-contenido.html',
  styleUrl: './admin-contenido.css',
})
export class AdminContenido {
  private readonly contenidoService = inject(ContenidoSitioService);
  private readonly router = inject(Router);

  contenido: ContenidoSitio = this.contenidoService.obtenerContenidoInicial();
  mensaje = '';

  constructor() {
    this.cargarContenido();
  }

  cargarContenido(): void {
    this.contenidoService.obtenerContenidoDesdeApi().subscribe({
      next: (contenido) => {
        this.contenido = contenido;
      },
      error: (error: HttpErrorResponse) => {
        this.mensaje = this.obtenerMensajeError(error, 'No se pudo cargar el contenido.');
      },
    });
  }

  guardar(): void {
    this.contenidoService.guardarContenidoDesdeApi(this.contenido).subscribe({
      next: (contenido) => {
        this.contenido = contenido;
        this.mensaje = 'Contenido actualizado correctamente.';
      },
      error: (error: HttpErrorResponse) => {
        this.mensaje = this.obtenerMensajeError(error, 'No se pudo guardar el contenido.');
      },
    });
  }

  private obtenerMensajeError(error: HttpErrorResponse, mensajePorDefecto: string): string {
    if (error.status === 401 || error.status === 403) {
      localStorage.removeItem('admin-token');
      localStorage.removeItem('admin-user');
      this.router.navigate(['/admin/login']);
      return 'Tu sesion vencio o no tiene permisos. Volve a iniciar sesion.';
    }

    if (error.error?.mensaje) {
      return error.error.mensaje;
    }

    return `${mensajePorDefecto} Revisa que el backend este levantado.`;
  }
}
