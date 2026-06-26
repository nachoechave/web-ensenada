import { Injectable } from '@angular/core';

import { Noticia } from '../models/noticia.model';

@Injectable({
  providedIn: 'root',
})
export class NoticiasService {
  private readonly storageKey = 'municipio-noticias';

  private readonly noticiasIniciales: Noticia[] = [
    {
      id: 1,
      titulo: 'El municipio avanza con nuevas obras en los barrios',
      bajada: 'Se realizaron trabajos de infraestructura en distintos puntos de la ciudad.',
      contenido:
        'El Municipio de Ensenada continúa desarrollando obras públicas orientadas a mejorar la calidad de vida de los vecinos y vecinas.',
      imagen: '/assets/ensenada-hero.jpg',
      categoria: 'Obras públicas',
      fecha: '2026-06-25',
      estado: 'Publicada',
    },
    {
      id: 2,
      titulo: 'Nueva agenda de actividades culturales',
      bajada: 'La ciudad contará con nuevas propuestas culturales durante la semana.',
      contenido:
        'El área de Cultura presentó una nueva agenda de actividades para vecinos, vecinas e instituciones de la ciudad.',
      imagen: '/assets/ensenada-hero.jpg',
      categoria: 'Cultura',
      fecha: '2026-06-24',
      estado: 'Publicada',
    },
    {
      id: 3,
      titulo: 'Inscripción abierta a talleres deportivos',
      bajada: 'Ya se encuentra disponible la inscripción a nuevas actividades deportivas.',
      contenido:
        'El Municipio informó la apertura de inscripción a talleres deportivos destinados a distintas edades.',
      imagen: '/assets/ensenada-hero.jpg',
      categoria: 'Deportes',
      fecha: '2026-06-23',
      estado: 'Borrador',
    },
  ];

  private noticias: Noticia[] = this.cargarNoticias();

  obtenerTodas(): Noticia[] {
    return [...this.noticias];
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
  }

  actualizar(id: number, noticiaActualizada: Omit<Noticia, 'id'>): void {
    this.noticias = this.noticias.map((noticia) =>
      noticia.id === id ? { ...noticiaActualizada, id } : noticia,
    );

    this.guardarNoticias();
  }

  eliminar(id: number): void {
    this.noticias = this.noticias.filter((noticia) => noticia.id !== id);
    this.guardarNoticias();
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

  private generarId(): number {
    const ids = this.noticias.map((noticia) => noticia.id);
    return ids.length > 0 ? Math.max(...ids) + 1 : 1;
  }
}