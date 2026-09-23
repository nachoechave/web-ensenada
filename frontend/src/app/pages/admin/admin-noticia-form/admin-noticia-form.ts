import { ChangeDetectorRef, Component, inject } from '@angular/core';
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
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly noticiasService = inject(NoticiasService);
  private readonly archivoUploadService = inject(ArchivoUploadService);

  noticiaId = Number(this.route.snapshot.paramMap.get('id'));
  esEdicion = !!this.noticiaId;
  imagenPreview = '';
  subiendoImagen = false;
  errorImagen = '';
  errorGuardado = '';
  guardando = false;
  categorias = ['Obras publicas', 'Cultura', 'Deportes', 'Salud', 'Educacion', 'Institucional'];

  noticia: NoticiaForm = {
    titulo: '',
    bajada: '',
    contenido: '',
    imagen: '',
    categoria: 'Institucional',
    fechaPublicacion: new Date().toISOString().slice(0, 10),
    estado: 'BORRADOR',
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
        this.cdr.markForCheck();
        this.asignarNoticia(noticia);
      },
      error: () => {
        this.cdr.markForCheck();
        alert('La noticia no existe.');
        this.router.navigate(['/admin/noticias']);
      },
    });
  }

  guardarNoticia(): void {
    if (this.guardando || this.subiendoImagen) return;
    this.errorGuardado = '';
    if (!this.noticia.imagen) {
      this.errorImagen = 'Primero subi o indica una imagen principal.';
      return;
    }

    if (
      ![
        this.noticia.titulo,
        this.noticia.bajada,
        this.noticia.contenido,
        this.noticia.categoria,
        this.noticia.fechaPublicacion,
      ].every((v) => v.trim())
    ) {
      this.errorGuardado = 'Completá todos los campos obligatorios.';
      return;
    }
    this.guardando = true;
    const peticion = this.esEdicion
      ? this.noticiasService.actualizarDesdeApi(this.noticiaId, this.noticia)
      : this.noticiasService.crearDesdeApi(this.noticia);

    peticion
      .pipe(
        finalize(() => {
          this.guardando = false;
          this.cdr.markForCheck();
        }),
      )
      .subscribe({
        next: () => {
          this.cdr.markForCheck();
          alert(
            this.esEdicion ? 'Noticia actualizada correctamente.' : 'Noticia creada correctamente.',
          );
          this.router.navigate(['/admin/noticias']);
        },
        error: (error: HttpErrorResponse) => {
          this.cdr.markForCheck();
          this.errorGuardado = this.obtenerMensajeUpload(error, 'No se pudo guardar la noticia.');
        },
      });
  }

  seleccionarImagen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];

    if (!archivo) {
      return;
    }

    if (!['image/jpeg', 'image/png'].includes(archivo.type) || archivo.size > 5 * 1024 * 1024) {
      this.errorImagen = 'Seleccioná una imagen JPEG o PNG de hasta 5 MB.';
      input.value = '';
      return;
    }

    this.errorImagen = '';
    this.subiendoImagen = true;

    this.archivoUploadService
      .subir('noticias', archivo)
      .pipe(
        finalize(() => {
          this.cdr.markForCheck();
          this.subiendoImagen = false;
          input.value = '';
        }),
      )
      .subscribe({
        next: (respuesta) => {
          this.cdr.markForCheck();
          this.noticia.imagen = respuesta.url;
          this.imagenPreview = respuesta.url;
        },
        error: (error: HttpErrorResponse) => {
          this.cdr.markForCheck();
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
      return 'La imagen es demasiado pesada. Usa una imagen de hasta 5 MB.';
    }

    if (error.error?.detail || error.error?.mensaje) {
      return error.error.detail ?? error.error.mensaje;
    }
    if (error.status === 400) return 'Revisá los campos y el formato de la imagen.';

    return `${mensajePorDefecto} Revisa que el backend este levantado.`;
  }
}
