import { Component, DestroyRef, inject } from '@angular/core';
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
  private readonly route = inject(ActivatedRoute);
  private readonly noticiasService = inject(NoticiasService);
  private readonly destroyRef = inject(DestroyRef);

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
        this.noticia = noticia;
      },
      error: () => {
        this.noticia = undefined;
      },
    });
  }
}
