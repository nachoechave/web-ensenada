import { ChangeDetectorRef, Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { Noticia } from '../../models/noticia.model';
import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { NoticiaCard } from '../../components/noticia-card/noticia-card';
import { NoticiasService } from '../../services/noticias.service';

@Component({
  selector: 'app-noticias',
  imports: [Navbar, Footer, NoticiaCard],
  templateUrl: './noticias.html',
  styleUrl: './noticias.css',
})
export class Noticias {
  private readonly noticiasService = inject(NoticiasService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  noticias: Noticia[] = [];

  constructor() {
    this.cargarNoticias();

    this.noticiasService.cambiosNoticias$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarNoticias());
  }

  private cargarNoticias(): void {
    this.noticiasService.obtenerPublicadasDesdeApi().subscribe({
      next: (noticias) => {
        this.noticias = noticias;
        this.cdr.detectChanges();
      },
      error: () => {
        this.noticias = [];
        this.cdr.detectChanges();
      },
    });
  }
}
