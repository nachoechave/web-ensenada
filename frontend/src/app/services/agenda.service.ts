import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, Subject, tap } from 'rxjs';

import { EventoAgenda } from '../models/evento-agenda.model';

@Injectable({
  providedIn: 'root',
})
export class AgendaService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8080/api';
  private readonly cambiosAgendaSubject = new Subject<void>();

  readonly cambiosAgenda$ = this.cambiosAgendaSubject.asObservable();

  obtenerEventosDesdeApi(): Observable<EventoAgenda[]> {
    return this.http.get<EventoAgenda[]>(`${this.apiUrl}/agenda`);
  }

  obtenerEventosAdminDesdeApi(): Observable<EventoAgenda[]> {
    return this.http.get<EventoAgenda[]>(`${this.apiUrl}/admin/agenda`);
  }

  crearDesdeApi(evento: Omit<EventoAgenda, 'id'>): Observable<EventoAgenda> {
    return this.http.post<EventoAgenda>(`${this.apiUrl}/admin/agenda`, evento).pipe(
      tap(() => this.notificarCambio()),
    );
  }

  eliminarDesdeApi(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/admin/agenda/${id}`);
  }

  notificarCambioAgenda(): void {
    this.notificarCambio();
  }

  private notificarCambio(): void {
    this.cambiosAgendaSubject.next();
  }
}
