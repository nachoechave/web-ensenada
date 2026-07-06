import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

type AccesoRapido = {
  numero: string;
  titulo: string;
  descripcion: string;
  ruta: string;
  destacado: boolean;
};

@Component({
  selector: 'app-accesos-rapidos',
  imports: [RouterLink],
  templateUrl: './accesos-rapidos.html',
  styleUrl: './accesos-rapidos.css',
})
export class AccesosRapidos {
  accesos: AccesoRapido[] = [
    {
      numero: '01',
      titulo: 'Trámites municipales',
      descripcion: 'Consultá requisitos, documentación y gestiones disponibles.',
      ruta: '/contacto',
      destacado: true,
    },
    {
      numero: '02',
      titulo: 'Turnos online',
      descripcion: 'Solicitá turnos para atención en áreas municipales.',
      ruta: '/areas',
      destacado: false,
    },
    {
      numero: '03',
      titulo: 'Reclamos y solicitudes',
      descripcion: 'Informá problemas en la vía pública o en tu barrio.',
      ruta: '/contacto',
      destacado: false,
    },
    {
      numero: '04',
      titulo: 'Agenda municipal',
      descripcion: 'Accedé a actividades, operativos y propuestas comunitarias.',
      ruta: '/agenda',
      destacado: false,
    },
  ];
}
