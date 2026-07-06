import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { DocumentoMunicipal } from '../../models/documento-municipal.model';
import { HaciendaService } from '../../services/hacienda.service';

@Component({
  selector: 'app-hacienda',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './hacienda.html',
  styleUrl: './hacienda.css',
})
export class Hacienda {
  private readonly haciendaService = inject(HaciendaService);

  documentos: DocumentoMunicipal[] = this.haciendaService.obtenerDocumentos();

  constructor() {
    this.haciendaService.obtenerDocumentosDesdeApi().subscribe({
      next: (documentos) => {
        this.documentos = documentos;
      },
      error: () => {
        this.documentos = this.haciendaService.obtenerDocumentos();
      },
    });
  }
}
