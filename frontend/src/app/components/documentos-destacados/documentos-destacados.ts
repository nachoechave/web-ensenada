import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

type DocumentoAcceso = {
  titulo: string;
  descripcion: string;
  ruta: string;
  externo?: boolean;
};

@Component({
  selector: 'app-documentos-destacados',
  imports: [RouterLink],
  templateUrl: './documentos-destacados.html',
  styleUrl: './documentos-destacados.css',
})
export class DocumentosDestacados {
  accesos: DocumentoAcceso[] = [
    {
      titulo: 'Boletín Oficial',
      descripcion: 'Publicaciones oficiales del Municipio organizadas por año.',
      ruta: 'https://boletinoficial.ensenada.gov.ar/index.php',
      externo: true,
    },
    {
      titulo: 'Hacienda',
      descripcion:
        'Situaciones económico financieras, ordenanzas y documentación fiscal.',
      ruta: '/hacienda',
    },
  ];
}
