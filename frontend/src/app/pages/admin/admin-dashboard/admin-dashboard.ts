import { Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';

import { AuthService } from '../../../services/auth.service';
import { BoletinOficialService } from '../../../services/boletin-oficial.service';
import { HaciendaService } from '../../../services/hacienda.service';
import { NoticiasService } from '../../../services/noticias.service';
import { DocumentoMunicipal } from '../../../models/documento-municipal.model';
import { Noticia } from '../../../models/noticia.model';

@Component({
  selector: 'app-admin-dashboard',
  imports: [RouterLink],
  templateUrl: './admin-dashboard.html',
  styleUrl: './admin-dashboard.css',
})
export class AdminDashboard {
  private readonly authService = inject(AuthService);
  private readonly noticiasService = inject(NoticiasService);
  private readonly boletinService = inject(BoletinOficialService);
  private readonly haciendaService = inject(HaciendaService);
  private readonly destroyRef = inject(DestroyRef);

  noticias: Noticia[] = [];
  boletines: DocumentoMunicipal[] = [];
  documentosHacienda: DocumentoMunicipal[] = [];
  mensaje = '';

  totalNoticias = this.noticias.length;
  noticiasPublicadas = this.noticias.filter(
    (noticia) => noticia.estado === 'Publicada',
  ).length;
  totalBoletines = this.boletines.length;
  totalHacienda = this.documentosHacienda.length;
  ultimasNoticias = this.noticias.slice(0, 4);

  constructor() {
    this.cargarResumen();

    this.noticiasService.cambiosNoticias$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarNoticias());
  }

  cargarResumen(): void {
    this.cargarNoticias();

    this.boletinService.obtenerBoletinesAdminDesdeApi().subscribe({
      next: (boletines) => {
        this.boletines = boletines;
        this.totalBoletines = boletines.length;
      },
      error: () => {
        this.boletines = [];
        this.totalBoletines = this.boletines.length;
        this.mensaje = 'No se pudo cargar parte del resumen desde el backend.';
      },
    });

    this.haciendaService.obtenerDocumentosAdminDesdeApi().subscribe({
      next: (documentos) => {
        this.documentosHacienda = documentos;
        this.totalHacienda = documentos.length;
      },
      error: () => {
        this.documentosHacienda = [];
        this.totalHacienda = this.documentosHacienda.length;
        this.mensaje = 'No se pudo cargar parte del resumen desde el backend.';
      },
    });
  }

  private cargarNoticias(): void {
    this.noticiasService.obtenerTodasDesdeApi().subscribe({
      next: (noticias) => {
        this.noticias = noticias;
        this.actualizarNoticias();
      },
      error: () => {
        this.noticias = [];
        this.mensaje = 'No se pudo cargar parte del resumen desde el backend.';
        this.actualizarNoticias();
      },
    });
  }

  puedeGestionar(roles: Parameters<AuthService['tieneRol']>[0]): boolean {
    return this.authService.tieneRol(roles);
  }

  private actualizarNoticias(): void {
    this.totalNoticias = this.noticias.length;
    this.noticiasPublicadas = this.noticias.filter(
      (noticia) => noticia.estado === 'Publicada',
    ).length;
    this.ultimasNoticias = this.noticias.slice(0, 4);
  }
}
