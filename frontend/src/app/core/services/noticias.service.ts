import { Injectable } from '@angular/core';
import { Noticia } from '../models/noticia.model';

@Injectable({
  providedIn: 'root',
})
export class NoticiasService {
  private noticias: Noticia[] = [
    {
      id: 1,
      titulo: 'El Municipio avanza con nuevas obras en los barrios',
      bajada: 'Se realizan trabajos de pavimentación, iluminación y mantenimiento urbano.',
      contenido:
        'La Municipalidad de Ensenada continúa ejecutando obras públicas en distintos puntos de la ciudad con el objetivo de mejorar la calidad de vida de los vecinos.',
      imagenUrl: 'assets/noticias/obra-publica.jpg',
      categoria: 'Obras Públicas',
      fechaPublicacion: '2026-06-25',
      autor: 'Prensa Municipal',
      publicada: true,
    },
    {
      id: 2,
      titulo: 'Nueva jornada cultural en el centro de la ciudad',
      bajada: 'Habrá música, feria de emprendedores y actividades para toda la familia.',
      contenido:
        'El área de Cultura invita a los vecinos a participar de una nueva jornada abierta con propuestas artísticas y recreativas.',
      imagenUrl: 'assets/noticias/cultura.jpg',
      categoria: 'Cultura',
      fechaPublicacion: '2026-06-24',
      autor: 'Prensa Municipal',
      publicada: true,
    },
    {
      id: 3,
      titulo: 'Inscripción abierta a actividades deportivas municipales',
      bajada: 'Los vecinos podrán anotarse a distintas disciplinas gratuitas.',
      contenido:
        'La Dirección de Deportes informó que ya se encuentra abierta la inscripción a las actividades deportivas municipales.',
      imagenUrl: 'assets/noticias/deportes.jpg',
      categoria: 'Deportes',
      fechaPublicacion: '2026-06-23',
      autor: 'Prensa Municipal',
      publicada: false,
    },
  ];

  obtenerNoticias(): Noticia[] {
    return this.noticias;
  }

  obtenerNoticiasPublicadas(): Noticia[] {
    return this.noticias.filter((noticia) => noticia.publicada);
  }

  obtenerNoticiaPorId(id: number): Noticia | undefined {
    return this.noticias.find((noticia) => noticia.id === id);
  }

  crearNoticia(nuevaNoticia: Omit<Noticia, 'id'>): void {
    const nuevoId =
      this.noticias.length > 0
        ? Math.max(...this.noticias.map((noticia) => noticia.id)) + 1
        : 1;

    this.noticias.push({
      id: nuevoId,
      ...nuevaNoticia,
    });
  }

  actualizarNoticia(id: number, noticiaActualizada: Partial<Noticia>): void {
    this.noticias = this.noticias.map((noticia) =>
      noticia.id === id ? { ...noticia, ...noticiaActualizada } : noticia,
    );
  }

  eliminarNoticia(id: number): void {
    this.noticias = this.noticias.filter((noticia) => noticia.id !== id);
  }
}