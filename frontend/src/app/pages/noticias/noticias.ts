import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

type Noticia = {
  id: number;
  titulo: string;
  resumen: string;
  categoria: string;
  fecha: string;
  imagen: string;
};

@Component({
  selector: 'app-noticias',
  imports: [RouterLink],
  templateUrl: './noticias.html',
  styleUrl: './noticias.css',
})
export class Noticias {
  noticias: Noticia[] = [
    {
      id: 1,
      titulo: 'Avanzan las obras de mejora urbana en distintos barrios',
      resumen:
        'El Municipio continúa trabajando en pavimento, iluminación y puesta en valor de espacios públicos.',
      categoria: 'Obras públicas',
      fecha: '17 de junio de 2026',
      imagen: '/assets/ensenada-hero.jpg',
    },
    {
      id: 2,
      titulo: 'Agenda cultural para este fin de semana',
      resumen:
        'Actividades libres y gratuitas para disfrutar en familia en distintos espacios de la ciudad.',
      categoria: 'Cultura',
      fecha: '15 de junio de 2026',
      imagen: '/assets/ensenada-hero.jpg',
    },
    {
      id: 3,
      titulo: 'Inscripción abierta a escuelas deportivas municipales',
      resumen:
        'Programas deportivos para niños, jóvenes y adultos en espacios municipales.',
      categoria: 'Deportes',
      fecha: '12 de junio de 2026',
      imagen: '/assets/ensenada-hero.jpg',
    },
    {
      id: 4,
      titulo: 'Nuevo operativo de salud comunitaria',
      resumen:
        'Se realizarán controles y asesoramiento sanitario en distintos barrios de la ciudad.',
      categoria: 'Salud',
      fecha: '10 de junio de 2026',
      imagen: '/assets/ensenada-hero.jpg',
    },
  ];
}