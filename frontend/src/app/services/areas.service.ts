import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, Subject, tap } from 'rxjs';

import { AreaMunicipal } from '../models/area-municipal.model';

@Injectable({
  providedIn: 'root',
})
export class AreasService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8080/api';
  private readonly cambiosAreasSubject = new Subject<void>();

  readonly cambiosAreas$ = this.cambiosAreasSubject.asObservable();

  private readonly areas: AreaMunicipal[] = [
    {
      id: 1,
      nombre: 'Secretaría de Inspección y Control Urbano',
      descripcion: 'Secretario: Marcos Omentari.',
      telefono: '(221) 460-0192 / (221) 615-6617',
      email: 'inspeccionycontrolurbano@ensenada.gov.ar',
      direccion: 'Presidente Perón y Sidoti',
      horario: 'Lunes a viernes de 8:00 a 16:00 hs',
    },
    {
      id: 2,
      nombre: 'Intendencia Municipal',
      descripcion: 'Intendente municipal: Mario Carlos Secco.',
      telefono: '(0221) 460-1771',
      email: 'intendente@ensenada.gov.ar',
      direccion: 'La Merced y Presidente Perón, 4.º piso',
      horario: 'No informado',
    },
    {
      id: 3,
      nombre: 'Secretaría de Servicios Públicos',
      descripcion: 'Secretario: Edgardo Reyes.',
      telefono: '(221) 469-1254',
      email: '',
      direccion: 'Corralón, Camino Néstor Kirchner y Rivadavia',
      horario: 'Lunes a viernes de 7:00 a 16:00 hs',
    },
    {
      id: 4,
      nombre: 'Secretaría Privada',
      descripcion: 'Secretaria: María Celina Ferella.',
      telefono: '(0221) 460-1771/2',
      email: 'intendente@ensenada.gov.ar',
      direccion: 'La Merced y Presidente Perón, 4.º piso, oficinas 401 y 402',
      horario: 'Lunes a viernes de 8:00 a 16:00 hs',
    },
    {
      id: 5,
      nombre: 'Secretaría de Gobierno',
      descripcion: 'Secretaria: Dra. María Alejandra Sabio.',
      telefono: '(0221) 469-4883',
      email: 'gobierno@ensenada.gov.ar',
      direccion: 'La Merced y Presidente Perón, 1.º piso, oficina 100',
      horario: 'Lunes a viernes de 8:00 a 16:00 hs',
    },
    {
      id: 6,
      nombre: 'Secretaría de Hacienda y Producción',
      descripcion: 'Secretaria: Rocío Basso.',
      telefono: '(0221) 460-1428',
      email: 'hacienda@ensenada.gov.ar',
      direccion: 'Presidente Perón 391',
      horario: 'Lunes a viernes de 8:00 a 15:00 hs',
    },
    {
      id: 7,
      nombre: 'Secretaría de Desarrollo Social',
      descripcion: 'Secretaria: Celina Ferella.',
      telefono: '(221) 469-1265',
      email: 'desarrollosocial@ensenada.gov.ar',
      direccion: 'Leandro N. Alem y Brasil',
      horario: 'Lunes a viernes de 8:00 a 16:00 hs',
    },
    {
      id: 8,
      nombre: 'Secretaría de Seguridad y Justicia',
      descripcion: 'Secretario: Martín Slobodian.',
      telefono: '(0221) 469-3155',
      email: '',
      direccion: 'No informada',
      horario: 'Lunes a viernes de 8:00 a 16:00 hs',
    },
    {
      id: 9,
      nombre: 'Secretaría de Gestión Pública',
      descripcion: 'Secretario: Agustín Duscovich.',
      telefono: '(221) 460-1770 / (221) 400-1504',
      email: 'prensamunicipalidadensenada@gmail.com',
      direccion: 'La Merced y Presidente Perón, 2.º piso, oficina 200',
      horario: 'Lunes a viernes de 8:00 a 16:00 hs',
    },
    {
      id: 10,
      nombre: 'Secretaría de Salud y Medio Ambiente',
      descripcion: 'Secretario de Salud: Juan Manuel López Ortega.',
      telefono: '(221) 469-0099 / (221) 469-2403',
      email: 'secsaludensenada@hotmail.com',
      direccion: 'San Martín y De La Paz',
      horario: 'Lunes a viernes de 8:00 a 16:00 hs',
    },
    {
      id: 11,
      nombre: 'Secretaría de Obras Públicas',
      descripcion: 'Secretario: José Alberto Núñez.',
      telefono: '(221) 469-3702',
      email: 'obrasyservicios@ensenada.gov.ar',
      direccion: 'La Merced y Presidente Perón, 3.º piso, oficina 300',
      horario: 'Lunes a viernes de 8:00 a 16:00 hs',
    },
  ];

  obtenerAreas(): AreaMunicipal[] {
    return [...this.areas];
  }

  obtenerAreasDesdeApi(): Observable<AreaMunicipal[]> {
    return this.http.get<AreaMunicipal[]>(`${this.apiUrl}/areas`);
  }

  obtenerAreasAdminDesdeApi(): Observable<AreaMunicipal[]> {
    return this.http.get<AreaMunicipal[]>(`${this.apiUrl}/admin/areas`);
  }

  crearDesdeApi(area: Omit<AreaMunicipal, 'id'>): Observable<AreaMunicipal> {
    return this.http.post<AreaMunicipal>(`${this.apiUrl}/admin/areas`, area).pipe(
      tap(() => this.notificarCambio()),
    );
  }

  actualizarDesdeApi(area: AreaMunicipal): Observable<AreaMunicipal> {
    return this.http.put<AreaMunicipal>(`${this.apiUrl}/admin/areas/${area.id}`, area).pipe(
      tap(() => this.notificarCambio()),
    );
  }

  eliminarDesdeApi(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/admin/areas/${id}`).pipe(
      tap(() => this.notificarCambio()),
    );
  }

  notificarCambioAreas(): void {
    this.notificarCambio();
  }

  private notificarCambio(): void {
    this.cambiosAreasSubject.next();
  }
}
