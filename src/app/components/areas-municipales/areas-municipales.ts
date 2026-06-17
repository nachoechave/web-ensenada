import { Component } from '@angular/core';

type AreaMunicipal = {
  numero: string;
  nombre: string;
  descripcion: string;
};

@Component({
  selector: 'app-areas-municipales',
  imports: [],
  templateUrl: './areas-municipales.html',
  styleUrl: './areas-municipales.css',
})
export class AreasMunicipales {
  areas: AreaMunicipal[] = [
    {
      numero: '01',
      nombre: 'Gobierno',
      descripcion:
        'Gestión institucional, atención ciudadana y coordinación municipal.',
    },
    {
      numero: '02',
      nombre: 'Obras públicas',
      descripcion:
        'Infraestructura urbana, mantenimiento y planificación de la ciudad.',
    },
    {
      numero: '03',
      nombre: 'Cultura',
      descripcion:
        'Talleres, actividades culturales, eventos y espacios comunitarios.',
    },
    {
      numero: '04',
      nombre: 'Deportes',
      descripcion:
        'Escuelas deportivas, programas barriales y actividades recreativas.',
    },
    {
      numero: '05',
      nombre: 'Desarrollo social',
      descripcion:
        'Acompañamiento, inclusión y políticas sociales para vecinos.',
    },
    {
      numero: '06',
      nombre: 'Salud',
      descripcion:
        'Programas de prevención, atención primaria y promoción comunitaria.',
    },
  ];
}