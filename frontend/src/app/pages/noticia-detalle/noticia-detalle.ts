import { Title, Meta } from '@angular/platform-browser';
import { ChangeDetectorRef, Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { Noticia } from '../../models/noticia.model';
import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { NoticiasService } from '../../services/noticias.service';

@Component({
  selector: 'app-noticia-detalle',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './noticia-detalle.html',
  styleUrl: './noticia-detalle.css',
})
export class NoticiaDetalle {
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly route = inject(ActivatedRoute);
  private readonly noticiasService = inject(NoticiasService);
  private readonly destroyRef = inject(DestroyRef);

  private readonly title = inject(Title);
  private readonly meta = inject(Meta);
  noticia?: Noticia;

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.cargarNoticia(id);

    this.noticiasService.cambiosNoticias$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarNoticia(id));
  }

  private cargarNoticia(id: number): void {
    this.noticiasService.obtenerPublicaPorIdDesdeApi(id).subscribe({
      next: (noticia) => {
        this.cdr.markForCheck();
        this.noticia = noticia;
        this.title.setTitle(noticia.titulo + ' | Municipalidad de Ensenada');
        this.meta.updateTag({ name: 'description', content: noticia.bajada });
        this.meta.updateTag({ property: 'og:title', content: noticia.titulo });
        this.meta.updateTag({ property: 'og:description', content: noticia.bajada });
        this.meta.updateTag({ property: 'og:type', content: 'article' });
      },
      error: () => {
        this.cdr.markForCheck();
        this.noticia = undefined;
      },
    });
  }
}
