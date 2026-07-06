import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize, timeout } from 'rxjs';

import { DocumentoMunicipal } from '../../../models/documento-municipal.model';
import { ArchivoUploadService } from '../../../services/archivo-upload.service';
import { HaciendaService } from '../../../services/hacienda.service';

type HaciendaForm = Omit<DocumentoMunicipal, 'id' | 'tipo'>;

@Component({
  selector: 'app-admin-hacienda',
  imports: [FormsModule],
  templateUrl: './admin-hacienda.html',
  styleUrl: './admin-hacienda.css',
})
export class AdminHacienda {
  private readonly haciendaService = inject(HaciendaService);
  private readonly archivoUploadService = inject(ArchivoUploadService);
  private readonly router = inject(Router);
  private readonly cdr = inject(ChangeDetectorRef);

  documentos: DocumentoMunicipal[] = [];
  mensaje = '';
  errorArchivo = '';
  subiendoArchivo = false;
  eliminandoId: number | null = null;
  archivoNombre = '';

  documento: HaciendaForm = this.crearFormularioVacio();

  constructor() {
    this.cargarDocumentos();
  }

  cargarDocumentos(limpiarMensaje = true): void {
    if (limpiarMensaje) {
      this.mensaje = '';
    }

    this.haciendaService.obtenerDocumentosAdminDesdeApi().subscribe({
      next: (documentos) => {
        this.documentos = documentos;
        this.cdr.detectChanges();
      },
      error: (error: HttpErrorResponse) => {
        this.documentos = [];
        this.mensaje = this.obtenerMensajeError(error, 'No se pudieron cargar los documentos.');
        this.cdr.detectChanges();
      },
    });
  }

  seleccionarArchivo(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];

    if (!archivo) {
      return;
    }

    if (!this.esPdfOImagen(archivo)) {
      this.errorArchivo = 'Selecciona un PDF o una imagen escaneada.';
      input.value = '';
      return;
    }

    this.errorArchivo = '';
    this.subiendoArchivo = true;

    this.archivoUploadService.subir('hacienda', archivo).pipe(
      timeout(15000),
      finalize(() => {
        this.subiendoArchivo = false;
        input.value = '';
        this.cdr.detectChanges();
      }),
    ).subscribe({
      next: (respuesta) => {
        this.documento.archivoUrl = respuesta.url;
        this.archivoNombre = respuesta.nombreOriginal;
      },
      error: (error: HttpErrorResponse) => {
        this.errorArchivo = this.obtenerMensajeError(error, 'No se pudo subir el archivo.');
      },
    });
  }

  guardar(): void {
    if (!this.documento.archivoUrl) {
      this.mensaje = 'Primero subi el archivo del documento.';
      return;
    }

    this.haciendaService.crearDesdeApi(this.documento).subscribe({
      next: () => {
        this.mensaje = 'Documento guardado correctamente.';
        this.documento = this.crearFormularioVacio();
        this.archivoNombre = '';
        this.cargarDocumentos(false);
      },
      error: (error: HttpErrorResponse) => {
        this.mensaje = this.obtenerMensajeError(error, 'No se pudo guardar el documento.');
      },
    });
  }

  eliminar(id: number): void {
    if (!confirm('Seguro que queres eliminar este documento?')) {
      return;
    }

    this.eliminandoId = id;
    this.mensaje = '';

    this.haciendaService.eliminarDesdeApi(id).pipe(
      timeout(15000),
      finalize(() => {
        this.eliminandoId = null;
        this.cdr.detectChanges();
      }),
    ).subscribe({
      next: () => {
        this.mensaje = 'Documento eliminado correctamente.';
        this.cargarDocumentos(false);
      },
      error: (error: HttpErrorResponse) => {
        this.mensaje = this.obtenerMensajeError(error, 'No se pudo eliminar el documento.');
      },
    });
  }

  private crearFormularioVacio(): HaciendaForm {
    return {
      titulo: '',
      fecha: new Date().toISOString().slice(0, 10),
      hora: '12:00',
      anio: new Date().getFullYear(),
      descripcion: '',
      archivoUrl: '',
    };
  }

  private esPdfOImagen(archivo: File): boolean {
    return archivo.type === 'application/pdf' || archivo.type.startsWith('image/');
  }

  private obtenerMensajeError(error: HttpErrorResponse, mensajePorDefecto: string): string {
    if (error.status === 401 || error.status === 403) {
      localStorage.removeItem('admin-token');
      localStorage.removeItem('admin-user');
      this.router.navigate(['/admin/login']);
      return 'Tu sesion vencio o no tiene permisos. Volve a iniciar sesion.';
    }

    if (error.status === 404) {
      return 'El documento no existe o ya fue eliminado.';
    }

    if (error.status === 413) {
      return 'El archivo es demasiado pesado. Usa un archivo de hasta 15 MB.';
    }

    if (error.error?.mensaje) {
      return error.error.mensaje;
    }

    return `${mensajePorDefecto} Revisa que el backend este levantado.`;
  }
}
