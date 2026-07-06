import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { DocumentoMunicipal } from '../models/documento-municipal.model';

@Injectable({
  providedIn: 'root',
})
export class HaciendaService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8080/api';
  private readonly storageKey = 'municipio-hacienda';

  private readonly documentosIniciales: DocumentoMunicipal[] = [
    {
      id: 1,
      titulo: 'Situación económico financiera del 01/01/2025 al 31/12/2025',
      fecha: '2026-04-06',
      hora: '12:05',
      anio: 2026,
      tipo: 'Hacienda',
      archivoUrl: '/assets/documentos/hacienda-ejemplo.svg',
    },
    {
      id: 2,
      titulo:
        'Proyecto de ordenanza del presupuesto general de gastos y cálculo de recursos del ejercicio 2026',
      fecha: '2026-01-05',
      hora: '14:58',
      anio: 2026,
      tipo: 'Hacienda',
      archivoUrl: '/assets/documentos/hacienda-ejemplo.svg',
    },
    {
      id: 3,
      titulo: 'Ordenanza Fiscal e Impositiva nueva, Ord. 4754/25',
      fecha: '2025-12-03',
      hora: '13:16',
      anio: 2025,
      tipo: 'Hacienda',
      archivoUrl: '/assets/documentos/hacienda-ejemplo.svg',
    },
  ];

  private documentos = this.cargarDocumentos();

  obtenerDocumentosDesdeApi(): Observable<DocumentoMunicipal[]> {
    return this.http.get<DocumentoMunicipal[]>(`${this.apiUrl}/hacienda`);
  }

  obtenerDocumentosAdminDesdeApi(): Observable<DocumentoMunicipal[]> {
    return this.http.get<DocumentoMunicipal[]>(`${this.apiUrl}/admin/hacienda`);
  }

  crearDesdeApi(documento: Omit<DocumentoMunicipal, 'id' | 'tipo'>): Observable<DocumentoMunicipal> {
    return this.http.post<DocumentoMunicipal>(`${this.apiUrl}/admin/hacienda`, documento);
  }

  eliminarDesdeApi(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/admin/hacienda/${id}`);
  }

  obtenerDocumentos(): DocumentoMunicipal[] {
    return [...this.documentos];
  }

  crear(documento: Omit<DocumentoMunicipal, 'id' | 'tipo'>): void {
    this.documentos = [
      {
        ...documento,
        id: this.generarId(),
        tipo: 'Hacienda',
      },
      ...this.documentos,
    ];

    this.guardarDocumentos();
  }

  eliminar(id: number): void {
    this.documentos = this.documentos.filter((documento) => documento.id !== id);
    this.guardarDocumentos();
  }

  private cargarDocumentos(): DocumentoMunicipal[] {
    const documentosGuardados = localStorage.getItem(this.storageKey);

    if (!documentosGuardados) {
      return this.documentosIniciales;
    }

    try {
      return JSON.parse(documentosGuardados) as DocumentoMunicipal[];
    } catch {
      return this.documentosIniciales;
    }
  }

  private guardarDocumentos(): void {
    localStorage.setItem(this.storageKey, JSON.stringify(this.documentos));
  }

  private generarId(): number {
    const ids = this.documentos.map((documento) => documento.id);
    return ids.length > 0 ? Math.max(...ids) + 1 : 1;
  }
}
