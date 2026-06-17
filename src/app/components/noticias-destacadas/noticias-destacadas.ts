import { Component } from '@angular/core';

type Noticia = {
  id: number;
  titulo: string;
  resumen: string;
  categoria: string;
  imagen: string;
};

@Component({
  selector: 'app-noticias-destacadas',
  imports: [],
  templateUrl: './noticias-destacadas.html',
  styleUrl: './noticias-destacadas.css',
})
export class NoticiasDestacadas {
  noticias: Noticia[] = [
    {
      id: 1,
      titulo: 'Avanzan las obras de mejora urbana en distintos barrios',
      resumen:
        'El Municipio continúa trabajando en pavimento, iluminación y puesta en valor de espacios públicos.',
      categoria: 'Obras públicas',
      imagen: '/assets/ensenada-hero.jpg',
    },
    {
      id: 2,
      titulo: 'Agenda cultural para este fin de semana',
      resumen: 'Actividades libres y gratuitas para disfrutar en familia.',
      categoria: 'Cultura',
      imagen: '/assets/ensenada-hero.jpg',
    },
    {
      id: 3,
      titulo: 'Inscripción abierta a escuelas deportivas municipales',
      resumen: 'Programas deportivos para niños, jóvenes y adultos.',
      categoria: 'Deportes',
      imagen: '/assets/ensenada-hero.jpg',
    },
  ];

  noticiaPrincipal = this.noticias[0];

  noticiasSecundarias = this.noticias.slice(1);
}