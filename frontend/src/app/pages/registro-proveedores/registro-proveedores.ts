import { Component } from '@angular/core';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { PortalIcon } from '../../components/portal-icon/portal-icon';

interface DocumentoProveedor {
  titulo: string;
  descripcion: string;
  url: string;
  icono: string;
  directo?: boolean;
}

@Component({
  selector: 'app-registro-proveedores',
  imports: [Navbar, Footer, PortalIcon],
  templateUrl: './registro-proveedores.html',
  styleUrl: './registro-proveedores.css',
})
export class RegistroProveedores {
  readonly documentos: DocumentoProveedor[] = [
    {
      titulo: 'Requisitos de inscripción',
      descripcion: 'Documentación requerida para personas físicas y sociedades.',
      url: 'https://www.ensenada.gov.ar/wp-content/uploads/2026/04/1-Requisitos-para-la-Inscripscion-Proveedores.pdf',
      icono: 'document',
      directo: true,
    },
    {
      titulo: 'Planilla de inscripción',
      descripcion: 'Formulario para solicitar el alta en el Registro Municipal de Proveedores.',
      url: 'https://www.ensenada.gov.ar/registro-municipal-de-proveedores/',
      icono: 'providers',
    },
    {
      titulo: 'Instructivo de la planilla',
      descripcion: 'Guía de ayuda para completar correctamente la documentación de inscripción.',
      url: 'https://www.ensenada.gov.ar/registro-municipal-de-proveedores/',
      icono: 'document',
    },
    {
      titulo: 'Descriptivo de la planilla',
      descripcion: 'Detalle de los campos y datos solicitados en la planilla de proveedores.',
      url: 'https://www.ensenada.gov.ar/registro-municipal-de-proveedores/',
      icono: 'invoice',
    },
    {
      titulo: 'Acreditación bancaria · F35',
      descripcion: 'Formulario de autorización para la acreditación de pagos en cuenta bancaria.',
      url: 'https://www.ensenada.gov.ar/registro-municipal-de-proveedores/',
      icono: 'receipt',
    },
  ];
}
