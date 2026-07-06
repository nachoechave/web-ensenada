import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { DocumentoMunicipal } from '../models/documento-municipal.model';

@Injectable({
  providedIn: 'root',
})
export class BoletinOficialService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8080/api';
  private readonly storageKey = 'municipio-boletines';

  private readonly boletinesIniciales: DocumentoMunicipal[] = [
    {
      id: 1,
      titulo: 'Boletín Oficial Municipal - Enero 2026',
      fecha: '2026-01-15',
      hora: '10:30',
      anio: 2026,
      tipo: 'Boletín oficial',
      descripcion: 'Publicación oficial de actos administrativos municipales.',
      archivoUrl: '/assets/documentos/boletin-oficial-ejemplo.svg',
    },
    {
      id: 2,
      titulo: 'Boletín Oficial Municipal - Diciembre 2025',
      fecha: '2025-12-12',
      hora: '11:15',
      anio: 2025,
      tipo: 'Boletín oficial',
      descripcion: 'Normativa, decretos y ordenanzas publicadas por el Municipio.',
      archivoUrl: '/assets/documentos/boletin-oficial-ejemplo.svg',
    },
    {
      id: 3,
      titulo: 'Boletín Oficial Municipal - Noviembre 2024',
      fecha: '2024-11-08',
      hora: '09:45',
      anio: 2024,
      tipo: 'Boletín oficial',
      descripcion: 'Archivo histórico de publicaciones oficiales.',
      archivoUrl: '/assets/documentos/boletin-oficial-ejemplo.svg',
    },
  ];

  private boletines = this.cargarBoletines();

  obtenerBoletinesDesdeApi(anio?: number): Observable<DocumentoMunicipal[]> {
    const params = anio ? `?anio=${anio}` : '';
    return this.http.get<DocumentoMunicipal[]>(`${this.apiUrl}/boletin-oficial${params}`);
  }

  obtenerBoletinesAdminDesdeApi(): Observable<DocumentoMunicipal[]> {
    return this.http.get<DocumentoMunicipal[]>(`${this.apiUrl}/admin/boletin-oficial`);
  }

  crearDesdeApi(boletin: Omit<DocumentoMunicipal, 'id' | 'tipo'>): Observable<DocumentoMunicipal> {
    return this.http.post<DocumentoMunicipal>(`${this.apiUrl}/admin/boletin-oficial`, boletin);
  }

  eliminarDesdeApi(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/admin/boletin-oficial/${id}`);
  }

  obtenerAnios(): number[] {
    return [...new Set(this.boletines.map((boletin) => boletin.anio))].sort(
      (actual, siguiente) => siguiente - actual,
    );
  }

  obtenerBoletines(anio?: number): DocumentoMunicipal[] {
    if (!anio) {
      return [...this.boletines];
    }

    return this.boletines.filter((boletin) => boletin.anio === anio);
  }

  crear(boletin: Omit<DocumentoMunicipal, 'id' | 'tipo'>): void {
    this.boletines = [
      {
        ...boletin,
        id: this.generarId(),
        tipo: 'Boletín oficial',
      },
      ...this.boletines,
    ];

    this.guardarBoletines();
  }

  eliminar(id: number): void {
    this.boletines = this.boletines.filter((boletin) => boletin.id !== id);
    this.guardarBoletines();
  }

  private cargarBoletines(): DocumentoMunicipal[] {
    const boletinesGuardados = localStorage.getItem(this.storageKey);

    if (!boletinesGuardados) {
      return this.boletinesIniciales;
    }

    try {
      return JSON.parse(boletinesGuardados) as DocumentoMunicipal[];
    } catch {
      return this.boletinesIniciales;
    }
  }

  private guardarBoletines(): void {
    localStorage.setItem(this.storageKey, JSON.stringify(this.boletines));
  }

  private generarId(): number {
    const ids = this.boletines.map((boletin) => boletin.id);
    return ids.length > 0 ? Math.max(...ids) + 1 : 1;
  }
}
