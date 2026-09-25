import { ChangeDetectorRef, Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';

import { cloneDefaultPortalContent } from '../../config/site-content';
import { Noticia } from '../../models/noticia.model';
import { NoticiasService } from '../../services/noticias.service';
import { PortalContentService } from '../../services/portal-content.service';

@Component({
  selector: 'app-noticias-destacadas',
  imports: [RouterLink],
  templateUrl: './noticias-destacadas.html',
  styleUrl: './noticias-destacadas.css',
})
export class NoticiasDestacadas {
  private readonly noticiasService = inject(NoticiasService);
  private readonly portalContentService = inject(PortalContentService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  contenido = cloneDefaultPortalContent();
  noticias: Noticia[] = [];
  error = '';
  noticiaPrincipal: Noticia | undefined;
  noticiasSecundarias: Noticia[] = [];

  constructor() {
    this.portalContentService
      .obtenerPublico()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((contenido) => (this.contenido = contenido));

    this.cargarNoticias();
    this.noticiasService.cambiosNoticias$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarNoticias());
  }

  private cargarNoticias(): void {
    this.noticiasService.obtenerDestacadasDesdeApi().subscribe({
      next: (noticias) => {
        this.error = '';
        this.noticias = noticias;
        this.actualizarDestacadas();
        this.cdr.detectChanges();
      },
      error: () => {
        this.error = 'Las noticias destacadas no están disponibles en este momento.';
        this.noticias = [];
        this.actualizarDestacadas();
        this.cdr.detectChanges();
      },
    });
  }

  private actualizarDestacadas(): void {
    this.noticiaPrincipal = this.noticias[0];
    this.noticiasSecundarias = this.noticias.slice(1, 3);
  }
}
