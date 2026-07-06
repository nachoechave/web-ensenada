import { ChangeDetectorRef, Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';

import { Noticia } from '../../models/noticia.model';
import { NoticiasService } from '../../services/noticias.service';

@Component({
  selector: 'app-noticias-destacadas',
  imports: [RouterLink],
  templateUrl: './noticias-destacadas.html',
  styleUrl: './noticias-destacadas.css',
})
export class NoticiasDestacadas {
  private readonly noticiasService = inject(NoticiasService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  noticias: Noticia[] = [];

  noticiaPrincipal: Noticia | undefined;

  noticiasSecundarias: Noticia[] = [];

  constructor() {
    this.cargarNoticias();

    this.noticiasService.cambiosNoticias$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarNoticias());
  }

  private cargarNoticias(): void {
    this.noticiasService.obtenerDestacadasDesdeApi().subscribe({
      next: (noticias) => {
        this.noticias = noticias;
        this.actualizarDestacadas();
        this.cdr.detectChanges();
      },
      error: () => {
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
