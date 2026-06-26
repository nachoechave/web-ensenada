import { Component } from '@angular/core';

type EventoAgenda = {
  id: number;
  dia: string;
  mes: string;
  titulo: string;
  lugar: string;
  categoria: string;
};

@Component({
  selector: 'app-agenda-municipal',
  imports: [],
  templateUrl: './agenda-municipal.html',
  styleUrl: './agenda-municipal.css',
})
export class AgendaMunicipal {
  eventos: EventoAgenda[] = [
    {
      id: 1,
      dia: '21',
      mes: 'JUN',
      titulo: 'Jornada cultural en Plaza Belgrano',
      lugar: 'Plaza Belgrano',
      categoria: 'Cultura',
    },
    {
      id: 2,
      dia: '23',
      mes: 'JUN',
      titulo: 'Operativo de salud comunitaria',
      lugar: 'Unidad sanitaria barrial',
      categoria: 'Salud',
    },
    {
      id: 3,
      dia: '25',
      mes: 'JUN',
      titulo: 'Encuentro deportivo municipal',
      lugar: 'Polideportivo Central',
      categoria: 'Deportes',
    },
  ];
}