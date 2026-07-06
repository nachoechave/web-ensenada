import { ChangeDetectorRef, Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router, RouterLink } from '@angular/router';
import { finalize, timeout } from 'rxjs';

import { Noticia } from '../../../models/noticia.model';
import { NoticiasService } from '../../../services/noticias.service';

@Component({
  selector: 'app-admin-noticias',
  imports: [RouterLink],
  templateUrl: './admin-noticias.html',
  styleUrl: './admin-noticias.css',
})
export class AdminNoticias {
  private readonly noticiasService = inject(NoticiasService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  noticias: Noticia[] = [];
  mensaje = '';
  eliminandoId: number | null = null;

  constructor() {
    this.cargarNoticias();

    this.noticiasService.cambiosNoticias$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarNoticias(false));
  }

  cargarNoticias(limpiarMensaje = true): void {
    if (limpiarMensaje) {
      this.mensaje = '';
    }

    this.noticiasService.obtenerTodasDesdeApi().subscribe({
      next: (noticias) => {
        this.noticias = noticias;
        this.cdr.detectChanges();
      },
      error: (error) => {
        this.noticias = [];
        this.mensaje = this.obtenerMensajeError(error, 'No se pudieron cargar las noticias desde el backend.');
        this.cdr.detectChanges();
      },
    });
  }

  eliminarNoticia(noticia: Noticia): void {
    const id = noticia.id;

    if (typeof id !== 'number' || Number.isNaN(id)) {
      this.mensaje = 'No se pudo eliminar la noticia porque no tiene un id valido.';
      return;
    }

    if (!confirm('Seguro que queres eliminar esta noticia?')) {
      return;
    }

    this.mensaje = '';
    this.eliminandoId = id;
    this.cdr.detectChanges();

    this.noticiasService.eliminarDesdeApi(id).pipe(
      timeout(15000),
      finalize(() => {
        this.eliminandoId = null;
        this.cdr.detectChanges();
      }),
    ).subscribe({
      next: () => {
        this.mensaje = 'Noticia eliminada correctamente.';
        this.noticiasService.notificarCambioNoticias();
        this.cargarNoticias(false);
        this.cdr.detectChanges();
      },
      error: (error) => {
        const mensaje = this.obtenerMensajeError(error, 'No se pudo eliminar la noticia.');
        alert(mensaje);
        this.mensaje = mensaje;
        this.cdr.detectChanges();
      },
    });
  }

  private obtenerMensajeError(error: { status?: number }, mensajePorDefecto: string): string {
    if (error.status === 401 || error.status === 403) {
      localStorage.removeItem('admin-token');
      localStorage.removeItem('admin-user');
      this.router.navigate(['/admin/login']);
      return 'Tu sesion vencio o no tiene permisos. Volve a iniciar sesion.';
    }

    if (error.status === 404) {
      return 'La noticia no existe o ya fue eliminada.';
    }

    return `${mensajePorDefecto} Revisa que el backend este levantado.`;
  }
}
