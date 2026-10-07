import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { DocumentoProveedor } from '../../../models/proveedor-documento.model';
import { ProveedoresService, errorProveedores } from '../../../services/proveedores.service';

@Component({
  selector: 'app-admin-proveedores',
  imports: [FormsModule],
  templateUrl: './admin-proveedores.html',
  styleUrl: './admin-proveedores.css',
})
export class AdminProveedores {
  private readonly service = inject(ProveedoresService);

  documentos = signal<DocumentoProveedor[]>([]);
  cargando = signal(true);
  guardando = signal(false);
  error = signal('');
  mensaje = signal('');

  titulo = '';
  descripcion = '';
  archivo: File | null = null;

  constructor() {
    this.cargar();
  }

  cargar(): void {
    this.cargando.set(true);
    this.service.listar(false).subscribe({
      next: (docs) => {
        this.documentos.set(docs);
        this.cargando.set(false);
      },
      error: (e) => {
        this.error.set(errorProveedores(e));
        this.cargando.set(false);
      },
    });
  }

  seleccionarArchivo(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.archivo = input.files?.[0] ?? null;
  }

  agregar(): void {
    if (!this.titulo.trim() || !this.archivo || this.guardando()) return;
    this.guardando.set(true);
    this.error.set('');
    this.mensaje.set('');
    this.service.crear(this.titulo.trim(), this.descripcion.trim(), this.archivo).subscribe({
      next: () => {
        this.titulo = '';
        this.descripcion = '';
        this.archivo = null;
        this.guardando.set(false);
        this.mensaje.set('Documento cargado correctamente.');
        this.cargar();
      },
      error: (e) => {
        this.guardando.set(false);
        this.error.set(errorProveedores(e));
      },
    });
  }

  guardar(documento: DocumentoProveedor): void {
    this.guardando.set(true);
    this.error.set('');
    this.service.editar(documento).subscribe({
      next: (actualizado) => {
        this.documentos.update((docs) => docs.map((d) => (d.id === actualizado.id ? actualizado : d)));
        this.guardando.set(false);
        this.mensaje.set('Cambios guardados.');
      },
      error: (e) => {
        this.guardando.set(false);
        this.error.set(errorProveedores(e));
      },
    });
  }

  eliminar(documento: DocumentoProveedor): void {
    if (!confirm(`¿Eliminar "${documento.titulo}"?`)) return;
    this.service.eliminar(documento.id).subscribe({
      next: () => this.cargar(),
      error: (e) => this.error.set(errorProveedores(e)),
    });
  }

  mover(index: number, direccion: -1 | 1): void {
    const destino = index + direccion;
    const docs = [...this.documentos()];
    if (destino < 0 || destino >= docs.length) return;
    [docs[index], docs[destino]] = [docs[destino], docs[index]];
    this.documentos.set(docs);
    this.service.ordenar(docs.map((d) => d.id)).subscribe({
      next: (ordenados) => this.documentos.set(ordenados),
      error: (e) => {
        this.error.set(errorProveedores(e));
        this.cargar();
      },
    });
  }
}
