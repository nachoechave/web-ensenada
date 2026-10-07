import { Component, inject, signal } from '@angular/core';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { PortalIcon } from '../../components/portal-icon/portal-icon';
import { DocumentoProveedor } from '../../models/proveedor-documento.model';
import { ProveedoresService } from '../../services/proveedores.service';

@Component({
  selector: 'app-registro-proveedores',
  imports: [Navbar, Footer, PortalIcon],
  templateUrl: './registro-proveedores.html',
  styleUrl: './registro-proveedores.css',
})
export class RegistroProveedores {
  private readonly service = inject(ProveedoresService);

  readonly documentos = signal<DocumentoProveedor[]>([]);
  readonly cargando = signal(true);
  readonly error = signal('');

  constructor() {
    this.service.listar(true).subscribe({
      next: (documentos) => {
        this.documentos.set(documentos);
        this.cargando.set(false);
      },
      error: () => {
        this.error.set('No se pudo cargar la documentación en este momento.');
        this.cargando.set(false);
      },
    });
  }

  icono(documento: DocumentoProveedor): string {
    if (documento.tipoMime.includes('spreadsheet') || documento.tipoMime.includes('excel')) return 'invoice';
    return 'document';
  }
}
