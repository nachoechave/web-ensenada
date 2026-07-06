import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, Subject, tap } from 'rxjs';

import { Noticia } from '../models/noticia.model';

@Injectable({
  providedIn: 'root',
})
export class NoticiasService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8080/api';
  private readonly storageKey = 'municipio-noticias';
  private readonly cambiosNoticiasSubject = new Subject<void>();

  readonly cambiosNoticias$ = this.cambiosNoticiasSubject.asObservable();

  private readonly noticiasIniciales: Noticia[] = [
    {
      id: 1,
      titulo: 'El Municipio avanza con nuevas obras en los barrios',
      bajada:
        'Se realizan trabajos de infraestructura, iluminación y mantenimiento urbano en distintos puntos de la ciudad.',
      contenido:
        'La Municipalidad de Ensenada continúa desarrollando obras públicas orientadas a mejorar la calidad de vida de los vecinos y vecinas.',
      imagen: '/assets/ensenada-hero.jpg',
      categoria: 'Obras públicas',
      fecha: '2026-06-25',
      estado: 'Publicada',
      destacada: true,
    },
    {
      id: 2,
      titulo: 'Nueva agenda de actividades culturales',
      bajada: 'La ciudad contará con propuestas culturales gratuitas durante la semana.',
      contenido:
        'El área de Cultura presentó una nueva agenda de actividades para vecinos, vecinas e instituciones.',
      imagen: '/assets/ensenada-hero.jpg',
      categoria: 'Cultura',
      fecha: '2026-06-24',
      estado: 'Publicada',
      destacada: true,
    },
    {
      id: 3,
      titulo: 'Inscripción abierta a talleres deportivos',
      bajada:
        'Ya se encuentra disponible la inscripción a nuevas actividades deportivas municipales.',
      contenido:
        'La Dirección de Deportes informó la apertura de inscripción a talleres destinados a distintas edades.',
      imagen: '/assets/ensenada-hero.jpg',
      categoria: 'Deportes',
      fecha: '2026-06-23',
      estado: 'Publicada',
      destacada: true,
    },
  ];

  private noticias: Noticia[] = this.cargarNoticias();

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
    return this.http.post<Noticia>(`${this.apiUrl}/admin/noticias`, noticia).pipe(
      tap(() => this.notificarCambio()),
    );
  }

  actualizarDesdeApi(id: number, noticia: Omit<Noticia, 'id'>): Observable<Noticia> {
    return this.http.put<Noticia>(`${this.apiUrl}/admin/noticias/${id}`, noticia).pipe(
      tap(() => this.notificarCambio()),
    );
  }

  eliminarDesdeApi(id: number): Observable<void> {
    return this.http.delete<void>(`${this.apiUrl}/admin/noticias/${id}`);
  }

  obtenerTodas(): Noticia[] {
    return [...this.noticias];
  }

  notificarCambioNoticias(): void {
    this.notificarCambio();
  }

  obtenerPublicadas(): Noticia[] {
    return this.noticias.filter((noticia) => noticia.estado === 'Publicada');
  }

  obtenerPorId(id: number): Noticia | undefined {
    return this.noticias.find((noticia) => noticia.id === id);
  }

  crear(noticia: Omit<Noticia, 'id'>): void {
    const nuevaNoticia: Noticia = {
      ...noticia,
      id: this.generarId(),
    };

    this.noticias = [nuevaNoticia, ...this.noticias];
    this.guardarNoticias();
    this.notificarCambio();
  }

  actualizar(id: number, noticiaActualizada: Omit<Noticia, 'id'>): void {
    this.noticias = this.noticias.map((noticia) =>
      noticia.id === id ? { ...noticiaActualizada, id } : noticia,
    );

    this.guardarNoticias();
    this.notificarCambio();
  }

  eliminar(id: number): void {
    this.noticias = this.noticias.filter((noticia) => noticia.id !== id);
    this.guardarNoticias();
    this.notificarCambio();
  }

  obtenerNoticiasPublicadas(): Noticia[] {
    return this.obtenerPublicadas();
  }

  obtenerNoticiaPorId(id: number): Noticia | undefined {
    return this.obtenerPorId(id);
  }

  private cargarNoticias(): Noticia[] {
    const noticiasGuardadas = localStorage.getItem(this.storageKey);

    if (!noticiasGuardadas) {
      return this.noticiasIniciales;
    }

    try {
      return JSON.parse(noticiasGuardadas) as Noticia[];
    } catch {
      return this.noticiasIniciales;
    }
  }

  private guardarNoticias(): void {
    localStorage.setItem(this.storageKey, JSON.stringify(this.noticias));
  }

  private notificarCambio(): void {
    this.cambiosNoticiasSubject.next();
  }

  private generarId(): number {
    const ids = this.noticias.map((noticia) => noticia.id);
    return ids.length > 0 ? Math.max(...ids) + 1 : 1;
  }
}
