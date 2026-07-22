import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';

type DocumentoProveedor = {
  titulo: string;
  descripcion: string;
  tipo: 'PDF' | 'DOC';
  archivoUrl: string;
};

@Component({
  selector: 'app-registro-proveedores',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './registro-proveedores.html',
  styleUrl: './registro-proveedores.css',
})
export class RegistroProveedores {
  documentos: DocumentoProveedor[] = [
    {
      titulo: 'Requisitos para la Inscripción Proveedores',
      descripcion: 'Condiciones y documentación necesaria para iniciar la inscripción.',
      tipo: 'PDF',
      archivoUrl: '/assets/proveedores/requisitos-inscripcion-proveedores.pdf',
    },
    {
      titulo: 'Planilla de Inscripción en el Registro de Proveedores',
      descripcion: 'Formulario para completar los datos de inscripción.',
      tipo: 'DOC',
      archivoUrl: '/assets/proveedores/planilla-inscripcion-registro-proveedores.doc',
    },
    {
      titulo: 'Instructivo Planilla de Inscripción en el Registro de Proveedores',
      descripcion: 'Guía para completar correctamente la planilla de inscripción.',
      tipo: 'DOC',
      archivoUrl: '/assets/proveedores/instructivo-planilla-inscripcion-proveedores.doc',
    },
    {
      titulo: 'Descriptivo Planilla de Inscripción en el Registro de Proveedores',
      descripcion: 'Detalle de campos y datos requeridos para la inscripción.',
      tipo: 'DOC',
      archivoUrl: '/assets/proveedores/descriptivo-planilla-inscripcion-proveedores.doc',
    },
    {
      titulo: 'Autorización de acreditación en cuenta bancaria - F35',
      descripcion: 'Formulario F35 para acreditación bancaria.',
      tipo: 'PDF',
      archivoUrl: '/assets/proveedores/autorizacion-acreditacion-cuenta-bancaria-f35.pdf',
    },
  ];
}
