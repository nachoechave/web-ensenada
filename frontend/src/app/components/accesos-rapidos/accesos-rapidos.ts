import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

type AccesoRapido = {
  numero: string;
  titulo: string;
  descripcion: string;
  ruta: string;
  destacado: boolean;
  externo?: boolean;
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
      titulo: 'Boletín Oficial',
      descripcion: 'Accedé al boletín oficial de la Municipalidad de Ensenada.',
      ruta: 'https://boletinoficial.ensenada.gov.ar/index.php',
      destacado: true,
      externo: true,
    },
    {
      numero: '02',
      titulo: 'Turnos veterinarios',
      descripcion: 'Solicitá un turno para los servicios veterinarios municipales.',
      ruta: 'https://www.ensenada.gov.ar/turnos/',
      destacado: false,
      externo: true,
    },
    {
      numero: '03',
      titulo: 'Registro Municipal de Proveedores',
      descripcion: 'Ingresá al registro municipal de proveedores.',
      ruta: '/registro-proveedores',
      destacado: false,
    },
    {
      numero: '04',
      titulo: 'Tasas Municipales',
      descripcion: 'Accedé a tu factura desde la web oficial.',
      ruta: 'https://pagos.ensenada.gov.ar/nuevo_index.php',
      destacado: false,
      externo: true,
    },
    {
      numero: '05',
      titulo: 'Situación Económica Financiera',
      descripcion: 'Consultá la información económica y financiera municipal.',
      ruta: '/hacienda',
      destacado: false,
    },
    {
      numero: '06',
      titulo: 'Boleta Digital',
      descripcion: 'Información para gestionar la boleta digital.',
      ruta: 'https://avisos.ensenada.gov.ar/',
      destacado: false,
      externo: true,
    },
  ];
}
