import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize, timeout } from 'rxjs';

import { AreaMunicipal } from '../../../models/area-municipal.model';
import { AreasService } from '../../../services/areas.service';

type AreaForm = Omit<AreaMunicipal, 'id'>;

@Component({
  selector: 'app-admin-areas',
  imports: [FormsModule],
  templateUrl: './admin-areas.html',
  styleUrl: './admin-areas.css',
})
export class AdminAreas {
  private readonly areasService = inject(AreasService);
  private readonly router = inject(Router);
  private readonly cdr = inject(ChangeDetectorRef);

  areas: AreaMunicipal[] = [];
  area: AreaForm = this.crearFormularioVacio();
  mensaje = '';
  guardando = false;
  eliminandoId: number | null = null;
  editandoId: number | null = null;

  constructor() {
    this.cargarAreas();
  }

  cargarAreas(limpiarMensaje = true): void {
    if (limpiarMensaje) {
      this.mensaje = '';
    }

    this.areasService.obtenerAreasAdminDesdeApi().subscribe({
      next: (areas) => {
        this.areas = areas;
        this.cdr.detectChanges();
      },
      error: (error: HttpErrorResponse) => {
        this.areas = [];
        this.mensaje = this.obtenerMensajeError(error, 'No se pudieron cargar las areas.');
        this.cdr.detectChanges();
      },
    });
  }

  guardar(): void {
    this.guardando = true;
    this.mensaje = '';

    const operacion = this.editandoId
      ? this.areasService.actualizarDesdeApi({ id: this.editandoId, ...this.area })
      : this.areasService.crearDesdeApi(this.area);

    operacion.pipe(
      timeout(15000),
      finalize(() => {
        this.guardando = false;
        this.cdr.detectChanges();
      }),
    ).subscribe({
      next: () => {
        this.mensaje = this.editandoId
          ? 'Area actualizada correctamente.'
          : 'Area guardada correctamente.';
        this.cancelarEdicion();
        this.areasService.notificarCambioAreas();
        this.cargarAreas(false);
      },
      error: (error: HttpErrorResponse) => {
        this.mensaje = this.obtenerMensajeError(error, 'No se pudo guardar el area.');
      },
    });
  }

  editar(area: AreaMunicipal): void {
    this.editandoId = area.id;
    this.area = {
      nombre: area.nombre,
      descripcion: area.descripcion,
      telefono: area.telefono,
      email: area.email,
      direccion: area.direccion ?? '',
      horario: area.horario,
    };
    this.mensaje = '';
  }

  cancelarEdicion(): void {
    this.editandoId = null;
    this.area = this.crearFormularioVacio();
  }

  eliminar(id: number): void {
    if (!confirm('Seguro que queres eliminar esta area?')) {
      return;
    }

    this.eliminandoId = id;
    this.mensaje = '';

    this.areasService.eliminarDesdeApi(id).pipe(
      timeout(15000),
      finalize(() => {
        this.eliminandoId = null;
        this.cdr.detectChanges();
      }),
    ).subscribe({
      next: () => {
        this.mensaje = 'Area eliminada correctamente.';
        if (this.editandoId === id) {
          this.cancelarEdicion();
        }
        this.areasService.notificarCambioAreas();
        this.cargarAreas(false);
      },
      error: (error: HttpErrorResponse) => {
        this.mensaje = this.obtenerMensajeError(error, 'No se pudo eliminar el area.');
      },
    });
  }

  private crearFormularioVacio(): AreaForm {
    return {
      nombre: '',
      descripcion: '',
      telefono: '',
      email: '',
      direccion: '',
      horario: 'Lunes a viernes de 8 a 14 hs',
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
      return 'El area no existe o ya fue eliminada.';
    }

    if (error.error?.mensaje) {
      return error.error.mensaje;
    }

    return `${mensajePorDefecto} Revisa que el backend este levantado.`;
  }
}
