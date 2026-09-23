import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Observable, finalize } from 'rxjs';
import { HaciendaService, errorHacienda } from '../../../services/hacienda.service';
import {
  ArchivoHacienda,
  PublicacionHacienda,
  PublicacionHaciendaRequest,
  tiposHacienda,
  TipoPublicacionHacienda,
} from '../../../models/hacienda.model';

@Component({
  selector: 'app-admin-hacienda-form',
  imports: [FormsModule, RouterLink],
  templateUrl: './admin-hacienda-form.html',
  styleUrl: './admin-hacienda-form.css',
})
export class AdminHaciendaForm {
  private readonly service = inject(HaciendaService);
  private readonly router = inject(Router);
  id = Number(inject(ActivatedRoute).snapshot.paramMap.get('id')) || null;
  readonly tipos = Object.entries(tiposHacienda).map(([value, label]) => ({
    value: value as TipoPublicacionHacienda,
    label,
  }));
  cargando = signal(false);
  cargaFallida = signal(false);
  ocupado = signal(false);
  error = signal('');
  mensaje = signal('');
  archivos = signal<ArchivoHacienda[]>([]);
  publicacion: PublicacionHaciendaRequest = {
    titulo: '',
    descripcion: '',
    tipo: 'SITUACION_ECONOMICO_FINANCIERA',
    fechaPublicacion: new Date().toLocaleDateString('en-CA'),
    estado: 'BORRADOR',
  };
  constructor() {
    if (this.id) {
      this.cargando.set(true);
      this.service.obtener(this.id, false).subscribe({
        next: (p) => {
          this.asignar(p);
          this.cargando.set(false);
        },
        error: (e) => {
          this.error.set(errorHacienda(e));
          this.cargaFallida.set(true);
          this.cargando.set(false);
        },
      });
    }
  }
  private asignar(p: PublicacionHacienda) {
    const { id, archivos, ...data } = p;
    this.id = id;
    this.publicacion = data;
    this.archivos.set(archivos);
  }
  guardar() {
    if (this.ocupado() || this.cargando() || this.cargaFallida()) return;
    if (!this.publicacion.titulo.trim() || !this.publicacion.fechaPublicacion) {
      this.error.set('Completá título y fecha.');
      return;
    }
    this.error.set('');
    this.mensaje.set('');
    this.ocupado.set(true);
    const nueva = !this.id;
    const request = this.id
      ? this.service.editar(this.id, this.publicacion)
      : this.service.crear(this.publicacion);
    request.pipe(finalize(() => this.ocupado.set(false))).subscribe({
      next: (p) => {
        this.asignar(p);
        this.mensaje.set('Publicación guardada.');
        if (nueva) this.router.navigate(['/admin/hacienda/editar', p.id]);
      },
      error: (e) => this.error.set(errorHacienda(e)),
    });
  }
  adjuntar(event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file || !this.id || this.ocupado()) return;
    const limits: Record<string, number> = {
      'application/pdf': 20,
      'image/jpeg': 5,
      'image/png': 5,
    };
    if (!limits[file.type] || file.size === 0 || file.size > limits[file.type] * 1024 * 1024) {
      this.error.set('Seleccioná un PDF de hasta 20 MB o JPEG/PNG de hasta 5 MB.');
      return;
    }
    this.operarArchivo(this.service.adjuntar(this.id, file), 'Documento adjuntado.');
  }
  quitar(a: ArchivoHacienda) {
    if (this.id && !this.ocupado() && confirm('¿Retirar este documento de la publicación?'))
      this.operarArchivo(this.service.quitar(this.id, a.id), 'Documento retirado.');
  }
  mover(index: number, delta: number) {
    if (!this.id || this.ocupado()) return;
    const ids = this.archivos().map((a) => a.id);
    const target = index + delta;
    if (target < 0 || target >= ids.length) return;
    [ids[index], ids[target]] = [ids[target], ids[index]];
    this.operarArchivo(this.service.ordenar(this.id, ids), 'Orden actualizado.');
  }
  private operarArchivo(request: Observable<PublicacionHacienda>, mensaje: string) {
    this.error.set('');
    this.mensaje.set('');
    this.ocupado.set(true);
    request.pipe(finalize(() => this.ocupado.set(false))).subscribe({
      // File edits must not overwrite unsaved title/description changes.
      next: (p) => {
        this.archivos.set(p.archivos);
        this.mensaje.set(mensaje);
      },
      error: (e) => this.error.set(errorHacienda(e)),
    });
  }
  descargar(a: ArchivoHacienda) {
    if (!this.id || this.ocupado()) return;
    this.ocupado.set(true);
    this.error.set('');
    this.service
      .descargar(this.id, a.id)
      .pipe(finalize(() => this.ocupado.set(false)))
      .subscribe({
        next: (blob) => {
          const url = URL.createObjectURL(blob);
          const link = document.createElement('a');
          link.href = url;
          const extension =
            a.tipoMime === 'application/pdf'
              ? '.pdf'
              : a.tipoMime === 'image/png'
                ? '.png'
                : '.jpg';
          link.download = a.nombreOriginal.replace(/\.[^.]*$/, '') + extension;
          link.click();
          setTimeout(() => URL.revokeObjectURL(url), 1000);
        },
        error: (e) => this.error.set(errorHacienda(e)),
      });
  }
}
