import { ChangeDetectorRef, Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { EventoAgenda } from '../../models/evento-agenda.model';
import { AgendaService } from '../../services/agenda.service';

@Component({
  selector: 'app-agenda',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './agenda.html',
  styleUrl: './agenda.css',
})
export class Agenda {
  private readonly agendaService = inject(AgendaService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  eventos: EventoAgenda[] = [];

  constructor() {
    this.cargarEventos();

    this.agendaService.cambiosAgenda$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarEventos());
  }

  private cargarEventos(): void {
    this.agendaService.obtenerEventosDesdeApi().subscribe({
      next: (eventos) => {
        this.eventos = eventos;
        this.cdr.detectChanges();
      },
      error: () => {
        this.eventos = [];
        this.cdr.detectChanges();
      },
    });
  }

  obtenerFechaLegible(fecha: string): string {
    return new Date(`${fecha}T00:00:00`).toLocaleDateString('es-AR', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  }
}
