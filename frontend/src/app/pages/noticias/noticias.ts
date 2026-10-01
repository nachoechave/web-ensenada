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
  error = '';
  pagina = 0;
  tamanioPagina = 12;
  totalPaginas = 0;
  totalElementos = 0;
  cargando = false;

  constructor() {
    this.cargarNoticias();

    this.noticiasService.cambiosNoticias$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarNoticias());
  }

  cambiarPagina(pagina: number): void {
    if (pagina < 0 || pagina >= this.totalPaginas || pagina === this.pagina) return;
    this.pagina = pagina;
    this.cargarNoticias();
    if (typeof window !== 'undefined' && typeof window.scrollTo === 'function') {
      try {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      } catch {
        // Algunos entornos de test no implementan scrollTo.
      }
    }
  }

  get paginasVisibles(): number[] {
    if (this.totalPaginas <= 1) return [];
    const inicio = Math.max(0, Math.min(this.pagina - 2, this.totalPaginas - 5));
    const fin = Math.min(this.totalPaginas, inicio + 5);
    return Array.from({ length: fin - inicio }, (_, index) => inicio + index);
  }

  private cargarNoticias(): void {
    this.cargando = true;
    this.noticiasService.obtenerPublicadasDesdeApi(this.pagina, this.tamanioPagina).subscribe({
      next: (resultado) => {
        this.error = '';
        this.noticias = resultado.content;
        this.pagina = resultado.page;
        this.totalPaginas = resultado.totalPages;
        this.totalElementos = resultado.totalElements;
        this.cargando = false;
        this.cdr.detectChanges();
      },
      error: () => {
        this.error = 'No se pudieron cargar las noticias. Intentá nuevamente más tarde.';
        this.noticias = [];
        this.totalPaginas = 0;
        this.totalElementos = 0;
        this.cargando = false;
        this.cdr.detectChanges();
      },
    });
  }
}
