import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { ArchivoUploadResponse, SeccionArchivo } from '../models/archivo-upload.model';

@Injectable({
  providedIn: 'root',
})
export class ArchivoUploadService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8080/api/admin/archivos';

  subir(seccion: SeccionArchivo, archivo: File): Observable<ArchivoUploadResponse> {
    const formData = new FormData();
    formData.append('archivo', archivo);

    return this.http.post<ArchivoUploadResponse>(`${this.apiUrl}/${seccion}`, formData);
  }
}
