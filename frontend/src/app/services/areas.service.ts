import { Injectable } from '@angular/core';

import { AreaMunicipal } from '../models/area-municipal.model';

@Injectable({
  providedIn: 'root',
})
export class AreasService {
  private readonly areas: AreaMunicipal[] = [
    {
      id: 1,
      nombre: 'Gobierno',
      descripcion:
        'Gestión institucional, atención ciudadana y coordinación municipal.',
      telefono: '0221 460-0001',
      email: 'gobierno@ensenada.gob.ar',
      horario: 'Lunes a viernes de 8 a 14 hs',
    },
    {
      id: 2,
      nombre: 'Obras públicas',
      descripcion:
        'Infraestructura urbana, mantenimiento y planificación de la ciudad.',
      telefono: '0221 460-0002',
      email: 'obraspublicas@ensenada.gob.ar',
      horario: 'Lunes a viernes de 8 a 14 hs',
    },
    {
      id: 3,
      nombre: 'Cultura',
      descripcion:
        'Talleres, actividades culturales, eventos y espacios comunitarios.',
      telefono: '0221 460-0003',
      email: 'cultura@ensenada.gob.ar',
      horario: 'Lunes a viernes de 9 a 15 hs',
    },
    {
      id: 4,
      nombre: 'Deportes',
      descripcion:
        'Escuelas deportivas, programas barriales y actividades recreativas.',
      telefono: '0221 460-0004',
      email: 'deportes@ensenada.gob.ar',
      horario: 'Lunes a viernes de 9 a 16 hs',
    },
    {
      id: 5,
      nombre: 'Desarrollo social',
      descripcion:
        'Acompañamiento, inclusión y políticas sociales para vecinos.',
      telefono: '0221 460-0005',
      email: 'desarrollosocial@ensenada.gob.ar',
      horario: 'Lunes a viernes de 8 a 14 hs',
    },
    {
      id: 6,
      nombre: 'Salud',
      descripcion:
        'Programas de prevención, atención primaria y promoción comunitaria.',
      telefono: '0221 460-0006',
      email: 'salud@ensenada.gob.ar',
      horario: 'Lunes a viernes de 8 a 14 hs',
    },
  ];

  obtenerAreas(): AreaMunicipal[] {
    return [...this.areas];
  }
}
