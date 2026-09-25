import { environment } from '../../environments/environment';
import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, catchError, of, shareReplay, tap } from 'rxjs';

import { cloneDefaultPortalContent } from '../config/site-content';
import { PortalContent } from '../models/portal-content.model';

export interface PortalImageUploadResponse {
  url: string;
  filename: string;
  mime: string;
  size: number;
}

@Injectable({ providedIn: 'root' })
export class PortalContentService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;
  private publicContent$?: Observable<PortalContent>;

  obtenerPublico(force = false): Observable<PortalContent> {
    if (!this.publicContent$ || force) {
      this.publicContent$ = this.http.get<PortalContent>(`${this.apiUrl}/site-content`).pipe(
        catchError(() => of(cloneDefaultPortalContent())),
        shareReplay({ bufferSize: 1, refCount: false }),
      );
    }
    return this.publicContent$;
  }

  obtenerAdmin(): Observable<PortalContent> {
    return this.http.get<PortalContent>(`${this.apiUrl}/admin/site-content`);
  }

  guardar(contenido: PortalContent): Observable<PortalContent> {
    return this.http.put<PortalContent>(`${this.apiUrl}/admin/site-content`, contenido).pipe(
      tap(() => {
        this.publicContent$ = undefined;
      }),
    );
  }

  subirImagen(archivo: File): Observable<PortalImageUploadResponse> {
    const formData = new FormData();
    formData.append('archivo', archivo);
    return this.http.post<PortalImageUploadResponse>(`${this.apiUrl}/admin/archivos/sitio`, formData);
  }
}
