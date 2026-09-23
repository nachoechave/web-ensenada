import { Injectable, inject } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { environment } from '../../environments/environment';
import { PublicacionHacienda, PublicacionHaciendaRequest } from '../models/hacienda.model';

@Injectable({ providedIn: 'root' })
export class HaciendaService {
  private readonly http = inject(HttpClient);
  private readonly api = environment.apiUrl;
  listar(publico = true) {
    return this.http.get<PublicacionHacienda[]>(`${this.api}/${publico ? '' : 'admin/'}hacienda`);
  }
  obtener(id: number, publico = true) {
    return this.http.get<PublicacionHacienda>(
      `${this.api}/${publico ? '' : 'admin/'}hacienda/${id}`,
    );
  }
  crear(body: PublicacionHaciendaRequest) {
    return this.http.post<PublicacionHacienda>(`${this.api}/admin/hacienda`, body);
  }
  editar(id: number, body: PublicacionHaciendaRequest) {
    return this.http.put<PublicacionHacienda>(`${this.api}/admin/hacienda/${id}`, body);
  }
  archivar(id: number) {
    return this.http.delete<void>(`${this.api}/admin/hacienda/${id}`);
  }
  adjuntar(id: number, file: File) {
    const body = new FormData();
    body.append('archivo', file);
    return this.http.post<PublicacionHacienda>(`${this.api}/admin/hacienda/${id}/archivos`, body);
  }
  quitar(id: number, archivoId: number) {
    return this.http.delete<PublicacionHacienda>(
      `${this.api}/admin/hacienda/${id}/archivos/${archivoId}`,
    );
  }
  ordenar(id: number, ids: number[]) {
    return this.http.put<PublicacionHacienda>(`${this.api}/admin/hacienda/${id}/archivos/orden`, {
      ids,
    });
  }
  descargar(id: number, archivoId: number) {
    return this.http.get(`${this.api}/admin/hacienda/${id}/archivos/${archivoId}/contenido`, {
      responseType: 'blob',
    });
  }
}
export function errorHacienda(error: HttpErrorResponse): string {
  if (error.status === 401) return 'Tu sesión venció. Volvé a iniciar sesión.';
  if (error.status === 403) return 'Tu cuenta no tiene permiso para esta acción.';
  if (error.status === 404) return 'La publicación o el documento no está disponible.';
  if (error.status === 413) return 'El archivo supera el tamaño permitido: PDF 20 MB, imagen 5 MB.';
  return (
    error.error?.detail ||
    'No se pudo completar la operación. Revisá los datos e intentá nuevamente.'
  );
}
