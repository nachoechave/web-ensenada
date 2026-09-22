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
  filtro = 'Todas';
  get filtradas(): Noticia[] {
    return this.noticias.filter(
      (n) =>
        this.filtro === 'Todas' ||
        (this.filtro === 'Destacadas' ? n.destacada : n.estado === this.filtro),
    );
  }
  cambiarEstado(noticia: Noticia): void {
    this.noticiasService
      .actualizarDesdeApi(noticia.id, {
        ...noticia,
        estado: noticia.estado === 'PUBLICADA' ? 'BORRADOR' : 'PUBLICADA',
      })
      .subscribe({
        error: () => {
          this.mensaje = 'No se pudo cambiar el estado.';
          this.cdr.markForCheck();
        },
      });
  }
  mensaje = '';
  archivandoId: number | null = null;

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
        this.mensaje = this.obtenerMensajeError(
          error,
          'No se pudieron cargar las noticias desde el backend.',
        );
        this.cdr.detectChanges();
      },
    });
  }

  archivarNoticia(noticia: Noticia): void {
    const id = noticia.id;

    if (typeof id !== 'number' || Number.isNaN(id)) {
      this.mensaje = 'No se pudo archivar la noticia porque no tiene un id valido.';
      return;
    }

    if (!confirm('Seguro que queres archivar esta noticia?')) {
      return;
    }

    this.mensaje = '';
    this.archivandoId = id;
    this.cdr.detectChanges();

    this.noticiasService
      .archivarDesdeApi(id)
      .pipe(
        timeout(15000),
        finalize(() => {
          this.archivandoId = null;
          this.cdr.detectChanges();
        }),
      )
      .subscribe({
        next: () => {
          this.mensaje = 'Noticia archivada correctamente.';
          this.noticiasService.notificarCambioNoticias();
          this.cdr.detectChanges();
        },
        error: (error) => {
          const mensaje = this.obtenerMensajeError(error, 'No se pudo archivar la noticia.');
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
      return 'La noticia no existe o ya fue archivada.';
    }

    return `${mensajePorDefecto} Revisa que el backend este levantado.`;
  }
}
