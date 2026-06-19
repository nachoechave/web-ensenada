import { Component } from '@angular/core';

type AccesoRapido = {
  numero: string;
  titulo: string;
  descripcion: string;
  destacado: boolean;
};

@Component({
  selector: 'app-accesos-rapidos',
  imports: [],
  templateUrl: './accesos-rapidos.html',
  styleUrl: './accesos-rapidos.css',
})
export class AccesosRapidos {
  accesos: AccesoRapido[] = [
    {
      numero: '01',
      titulo: 'Trámites municipales',
      descripcion: 'Consultá requisitos, documentación y gestiones disponibles.',
      destacado: true,
    },
    {
      numero: '02',
      titulo: 'Turnos online',
      descripcion: 'Solicitá turnos para atención en áreas municipales.',
      destacado: false,
    },
    {
      numero: '03',
      titulo: 'Reclamos y solicitudes',
      descripcion: 'Informá problemas en la vía pública o en tu barrio.',
      destacado: false,
    },
    {
      numero: '04',
      titulo: 'Pagos y consultas',
      descripcion: 'Accedé a pagos, tasas y consultas administrativas.',
      destacado: false,
    },
  ];
}