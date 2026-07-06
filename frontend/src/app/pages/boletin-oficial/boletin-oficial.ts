import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { DocumentoMunicipal } from '../../models/documento-municipal.model';
import { BoletinOficialService } from '../../services/boletin-oficial.service';

@Component({
  selector: 'app-boletin-oficial',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './boletin-oficial.html',
  styleUrl: './boletin-oficial.css',
})
export class BoletinOficial {
  private readonly boletinService = inject(BoletinOficialService);

  anios: number[] = this.boletinService.obtenerAnios();
  anioSeleccionado = this.anios[0];
  boletines: DocumentoMunicipal[] = this.boletinService.obtenerBoletines(
    this.anioSeleccionado,
  );

  constructor() {
    this.cargarBoletines();
  }

  seleccionarAnio(anio: number): void {
    this.anioSeleccionado = anio;
    this.cargarBoletines();
  }

  private cargarBoletines(): void {
    this.boletinService.obtenerBoletinesDesdeApi(this.anioSeleccionado).subscribe({
      next: (boletines) => {
        this.boletines = boletines;
        this.anios = this.obtenerAniosDesdeBoletines(boletines);
      },
      error: () => {
        this.anios = this.boletinService.obtenerAnios();
        this.boletines = this.boletinService.obtenerBoletines(this.anioSeleccionado);
      },
    });
  }

  private obtenerAniosDesdeBoletines(boletines: DocumentoMunicipal[]): number[] {
    const anios = [...new Set([...boletines.map((boletin) => boletin.anio), ...this.anios])];
    return anios.sort((actual, siguiente) => siguiente - actual);
  }
}
