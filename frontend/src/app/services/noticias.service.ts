import { environment } from '../../environments/environment';
import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, Subject, tap } from 'rxjs';

import { Noticia } from '../models/noticia.model';

@Injectable({
  providedIn: 'root',
})
export class NoticiasService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;
  private readonly cambiosNoticiasSubject = new Subject<void>();

  readonly cambiosNoticias$ = this.cambiosNoticiasSubject.asObservable();

  obtenerPublicadasDesdeApi(): Observable<Noticia[]> {
    return this.http.get<Noticia[]>(`${this.apiUrl}/noticias`);
  }

  obtenerDestacadasDesdeApi(): Observable<Noticia[]> {
    return this.http.get<Noticia[]>(`${this.apiUrl}/noticias/destacadas`);
  }

  obtenerTodasDesdeApi(): Observable<Noticia[]> {
    return this.http.get<Noticia[]>(`${this.apiUrl}/admin/noticias`);
  }

  obtenerPublicaPorIdDesdeApi(id: number): Observable<Noticia> {
    return this.http.get<Noticia>(`${this.apiUrl}/noticias/${id}`);
  }

  obtenerAdminPorIdDesdeApi(id: number): Observable<Noticia> {
    return this.http.get<Noticia>(`${this.apiUrl}/admin/noticias/${id}`);
  }

  crearDesdeApi(noticia: Omit<Noticia, 'id'>): Observable<Noticia> {
    return this.http
      .post<Noticia>(`${this.apiUrl}/admin/noticias`, noticia)
      .pipe(tap(() => this.notificarCambio()));
  }

  actualizarDesdeApi(id: number, noticia: Omit<Noticia, 'id'>): Observable<Noticia> {
    return this.http
      .put<Noticia>(`${this.apiUrl}/admin/noticias/${id}`, noticia)
      .pipe(tap(() => this.notificarCambio()));
  }

  archivarDesdeApi(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/admin/noticias/${id}`);
  }

  notificarCambioNoticias(): void {
    this.notificarCambio();
  }

  private notificarCambio(): void {
    this.cambiosNoticiasSubject.next();
  }
}
