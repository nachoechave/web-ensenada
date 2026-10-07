import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { environment } from '../../environments/environment';
import { DocumentoProveedor } from '../models/proveedor-documento.model';

@Injectable({ providedIn: 'root' })
export class ProveedoresService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;

  listar(publico = true) {
    return this.http.get<DocumentoProveedor[]>(
      `${this.api}/${publico ? '' : 'admin/'}proveedores/documentos`,
    );
  }

  crear(titulo: string, descripcion: string, archivo: File) {
    const body = new FormData();
    body.append('titulo', titulo);
    body.append('descripcion', descripcion);
    body.append('archivo', archivo);
    return this.http.post<DocumentoProveedor>(`${this.api}/admin/proveedores/documentos`, body);
  }

  editar(documento: DocumentoProveedor) {
    return this.http.put<DocumentoProveedor>(
      `${this.api}/admin/proveedores/documentos/${documento.id}`,
      {
        titulo: documento.titulo,
        descripcion: documento.descripcion,
        activo: documento.activo,
      },
    );
  }

  eliminar(id: number) {
    return this.http.delete<void>(`${this.api}/admin/proveedores/documentos/${id}`);
  }

  ordenar(ids: number[]) {
    return this.http.put<DocumentoProveedor[]>(
      `${this.api}/admin/proveedores/documentos/orden`,
      { ids },
    );
  }
}

export function errorProveedores(error: HttpErrorResponse): string {
  if (error.status === 401) return 'Tu sesión venció. Volvé a iniciar sesión.';
  if (error.status === 403) return 'Tu cuenta no tiene permiso para administrar proveedores.';
  if (error.status === 413) return 'El archivo supera el tamaño permitido.';
  return error.error?.detail || 'No se pudo completar la operación.';
}
