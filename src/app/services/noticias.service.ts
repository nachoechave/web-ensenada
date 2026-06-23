import { Injectable } from '@angular/core';
import { Noticia } from '../models/noticia.model';

@Injectable({
  providedIn: 'root',
})
export class NoticiasService {
  private noticias: Noticia[] = [
    {
      id: 1,
      titulo: 'El Municipio continúa con obras de infraestructura en los barrios',
      bajada:
        'Se llevan adelante trabajos de pavimento, iluminación y mantenimiento urbano en distintos puntos de la ciudad.',
      contenido: [
        'La Municipalidad de Ensenada continúa desarrollando obras de infraestructura urbana con el objetivo de mejorar la calidad de vida de los vecinos y vecinas.',
        'Los trabajos incluyen tareas de pavimentación, reparación de calles, colocación de luminarias LED y mantenimiento de espacios públicos.',
        'Estas acciones forman parte de un plan integral que busca fortalecer el crecimiento ordenado de la ciudad y garantizar mejores servicios en cada barrio.',
      ],
      imagen: 'assets/noticias/obra-municipal.jpg',
      fecha: '23 de junio de 2026',
      categoria: 'Obras Públicas',
    },
    {
      id: 2,
      titulo: 'Nueva jornada cultural en Ensenada',
      bajada:
        'Habrá espectáculos, actividades recreativas y propuestas para toda la familia.',
      contenido: [
        'Este fin de semana se realizará una nueva jornada cultural abierta a toda la comunidad.',
        'La propuesta contará con música en vivo, feria de emprendedores, actividades para niños y presentaciones artísticas locales.',
        'Desde el Municipio se invita a los vecinos y vecinas a participar de esta iniciativa que promueve la cultura y el encuentro comunitario.',
      ],
      imagen: 'assets/noticias/cultura.jpg',
      fecha: '22 de junio de 2026',
      categoria: 'Cultura',
    },
    {
      id: 3,
      titulo: 'Avanzan las actividades deportivas municipales',
      bajada:
        'Las escuelas deportivas siguen creciendo con propuestas gratuitas para todas las edades.',
      contenido: [
        'El área de Deportes continúa impulsando actividades gratuitas en diferentes espacios municipales.',
        'Las propuestas incluyen fútbol, vóley, básquet, atletismo, gimnasia y otras disciplinas destinadas a niños, jóvenes y adultos.',
        'El objetivo es fomentar la vida saludable, la inclusión y la participación comunitaria a través del deporte.',
      ],
      imagen: 'assets/noticias/deportes.jpg',
      fecha: '21 de junio de 2026',
      categoria: 'Deportes',
    },
  ];

  obtenerNoticias(): Noticia[] {
    return this.noticias;
  }

  obtenerNoticiaPorId(id: number): Noticia | undefined {
    return this.noticias.find((noticia) => noticia.id === id);
  }
}