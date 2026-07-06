import { Component, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';

import { Noticia } from '../../../models/noticia.model';
import { ArchivoUploadService } from '../../../services/archivo-upload.service';
import { NoticiasService } from '../../../services/noticias.service';

type NoticiaForm = Omit<Noticia, 'id'>;

@Component({
  selector: 'app-admin-noticia-form',
  imports: [FormsModule, RouterLink],
  templateUrl: './admin-noticia-form.html',
  styleUrl: './admin-noticia-form.css',
})
export class AdminNoticiaForm {
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly noticiasService = inject(NoticiasService);
  private readonly archivoUploadService = inject(ArchivoUploadService);

  noticiaId = Number(this.route.snapshot.paramMap.get('id'));
  esEdicion = !!this.noticiaId;
  imagenPreview = '';
  subiendoImagen = false;
  errorImagen = '';
  categorias = ['Obras publicas', 'Cultura', 'Deportes', 'Salud', 'Educacion', 'Institucional'];

  noticia: NoticiaForm = {
    titulo: '',
    bajada: '',
    contenido: '',
    imagen: '',
    categoria: 'Institucional',
    fecha: new Date().toISOString().slice(0, 10),
    estado: 'Borrador',
    destacada: false,
  };

  constructor() {
    if (this.esEdicion) {
      this.cargarNoticia();
    }
  }

  cargarNoticia(): void {
    this.noticiasService.obtenerAdminPorIdDesdeApi(this.noticiaId).subscribe({
      next: (noticia) => {
        this.asignarNoticia(noticia);
      },
      error: () => {
        alert('La noticia no existe.');
        this.router.navigate(['/admin/noticias']);
      },
    });
  }

  guardarNoticia(): void {
    if (!this.noticia.imagen) {
      this.errorImagen = 'Primero subi o indica una imagen principal.';
      return;
    }

    const peticion = this.esEdicion
      ? this.noticiasService.actualizarDesdeApi(this.noticiaId, this.noticia)
      : this.noticiasService.crearDesdeApi(this.noticia);

    peticion.subscribe({
      next: () => {
        alert(this.esEdicion ? 'Noticia actualizada correctamente.' : 'Noticia creada correctamente.');
        this.router.navigate(['/admin/noticias']);
      },
      error: (error: HttpErrorResponse) => {
        alert(this.obtenerMensajeUpload(error, 'No se pudo guardar la noticia.'));
      },
    });
  }

  seleccionarImagen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];

    if (!archivo) {
      return;
    }

    if (!archivo.type.startsWith('image/')) {
      this.errorImagen = 'El archivo seleccionado debe ser una imagen.';
      input.value = '';
      return;
    }

    this.errorImagen = '';
    this.subiendoImagen = true;

    this.archivoUploadService.subir('noticias', archivo).pipe(
      finalize(() => {
        this.subiendoImagen = false;
        input.value = '';
      }),
    ).subscribe({
      next: (respuesta) => {
        this.noticia.imagen = respuesta.url;
        this.imagenPreview = respuesta.url;
      },
      error: (error: HttpErrorResponse) => {
        this.errorImagen = this.obtenerMensajeUpload(error, 'No se pudo subir la imagen.');
      },
    });
  }

  private asignarNoticia(noticia: Noticia): void {
    const { id, ...noticiaSinId } = noticia;
    this.noticia = noticiaSinId;
    this.imagenPreview = this.noticia.imagen;
  }

  private obtenerMensajeUpload(error: HttpErrorResponse, mensajePorDefecto: string): string {
    if (error.status === 401 || error.status === 403) {
      localStorage.removeItem('admin-token');
      localStorage.removeItem('admin-user');
      this.router.navigate(['/admin/login']);
      return 'Tu sesion vencio o no tiene permisos. Volve a iniciar sesion.';
    }

    if (error.status === 413) {
      return 'La imagen es demasiado pesada. Usa una imagen de hasta 15 MB.';
    }

    if (error.error?.mensaje) {
      return error.error.mensaje;
    }

    return `${mensajePorDefecto} Revisa que el backend este levantado.`;
  }
}
