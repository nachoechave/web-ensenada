import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize, timeout } from 'rxjs';

import { EventoAgenda } from '../../../models/evento-agenda.model';
import { AgendaService } from '../../../services/agenda.service';

type EventoForm = Omit<EventoAgenda, 'id'>;

@Component({
  selector: 'app-admin-agenda',
  imports: [FormsModule],
  templateUrl: './admin-agenda.html',
  styleUrl: './admin-agenda.css',
})
export class AdminAgenda {
  private readonly agendaService = inject(AgendaService);
  private readonly router = inject(Router);
  private readonly cdr = inject(ChangeDetectorRef);

  eventos: EventoAgenda[] = [];
  mensaje = '';
  guardando = false;
  eliminandoId: number | null = null;
  categorias = ['Cultura', 'Deportes', 'Salud', 'Educacion', 'Institucional', 'Operativo'];

  evento: EventoForm = this.crearFormularioVacio();

  constructor() {
    this.cargarEventos();
  }

  cargarEventos(limpiarMensaje = true): void {
    if (limpiarMensaje) {
      this.mensaje = '';
    }

    this.agendaService.obtenerEventosAdminDesdeApi().subscribe({
      next: (eventos) => {
        this.eventos = eventos;
        this.cdr.detectChanges();
      },
      error: (error: HttpErrorResponse) => {
        this.eventos = [];
        this.mensaje = this.obtenerMensajeError(error, 'No se pudieron cargar los eventos.');
        this.cdr.detectChanges();
      },
    });
  }

  guardar(): void {
    this.guardando = true;
    this.mensaje = '';

    this.agendaService.crearDesdeApi(this.evento).pipe(
      timeout(15000),
      finalize(() => {
        this.guardando = false;
        this.cdr.detectChanges();
      }),
    ).subscribe({
      next: () => {
        this.mensaje = 'Evento guardado correctamente.';
        this.evento = this.crearFormularioVacio();
        this.agendaService.notificarCambioAgenda();
        this.cargarEventos(false);
      },
      error: (error: HttpErrorResponse) => {
        this.mensaje = this.obtenerMensajeError(error, 'No se pudo guardar el evento.');
      },
    });
  }

  eliminar(id: number): void {
    if (!confirm('Seguro que queres eliminar este evento?')) {
      return;
    }

    this.eliminandoId = id;
    this.mensaje = '';

    this.agendaService.eliminarDesdeApi(id).pipe(
      timeout(15000),
      finalize(() => {
        this.eliminandoId = null;
        this.cdr.detectChanges();
      }),
    ).subscribe({
      next: () => {
        this.mensaje = 'Evento eliminado correctamente.';
        this.agendaService.notificarCambioAgenda();
        this.cargarEventos(false);
      },
      error: (error: HttpErrorResponse) => {
        this.mensaje = this.obtenerMensajeError(error, 'No se pudo eliminar el evento.');
      },
    });
  }

  obtenerFechaLegible(fecha: string): string {
    return new Date(`${fecha}T00:00:00`).toLocaleDateString('es-AR', {
      day: 'numeric',
      month: 'long',
      year: 'numeric',
    });
  }

  private crearFormularioVacio(): EventoForm {
    return {
      fecha: new Date().toISOString().slice(0, 10),
      horario: '',
      titulo: '',
      lugar: '',
      categoria: 'Cultura',
      descripcion: '',
    };
  }

  private obtenerMensajeError(error: HttpErrorResponse, mensajePorDefecto: string): string {
    if (error.status === 401 || error.status === 403) {
      localStorage.removeItem('admin-token');
      localStorage.removeItem('admin-user');
      this.router.navigate(['/admin/login']);
      return 'Tu sesion vencio o no tiene permisos. Volve a iniciar sesion.';
    }

    if (error.status === 404) {
      return 'El evento no existe o ya fue eliminado.';
    }

    if (error.error?.mensaje) {
      return error.error.mensaje;
    }

    return `${mensajePorDefecto} Revisa que el backend este levantado.`;
  }
}
