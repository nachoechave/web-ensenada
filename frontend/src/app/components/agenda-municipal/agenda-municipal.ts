import { ChangeDetectorRef, Component, DestroyRef, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { EventoAgenda } from '../../models/evento-agenda.model';
import { AgendaService } from '../../services/agenda.service';

@Component({
  selector: 'app-agenda-municipal',
  imports: [RouterLink],
  templateUrl: './agenda-municipal.html',
  styleUrl: './agenda-municipal.css',
})
export class AgendaMunicipal {
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
        this.eventos = eventos.slice(0, 3);
        this.cdr.detectChanges();
      },
      error: () => {
        this.eventos = [];
        this.cdr.detectChanges();
      },
    });
  }

  obtenerDia(fecha: string): string {
    return new Date(`${fecha}T00:00:00`).toLocaleDateString('es-AR', {
      day: '2-digit',
    });
  }

  obtenerMes(fecha: string): string {
    return new Date(`${fecha}T00:00:00`)
      .toLocaleDateString('es-AR', { month: 'short' })
      .replace('.', '')
      .toUpperCase();
  }
}
