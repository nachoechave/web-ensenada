import { DOCUMENT } from '@angular/common';
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
  private readonly document = inject(DOCUMENT);
  private readonly title = inject(Title);
  private readonly meta = inject(Meta);

  noticia?: Noticia;
  imagenActiva = '';
  private readonly identificador = this.route.snapshot.paramMap.get('id') ?? '';

  constructor() {
    this.cargarNoticia();

    this.noticiasService.cambiosNoticias$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarNoticia());
  }

  private cargarNoticia(): void {
    this.noticiasService.obtenerPublicaDesdeApi(this.identificador).subscribe({
      next: (noticia) => {
        this.noticia = noticia;
        this.imagenActiva = noticia.imagen;
        this.actualizarSeo(noticia);
        this.normalizarUrl(noticia);
        this.cdr.markForCheck();
      },
      error: () => {
        this.noticia = undefined;
        this.title.setTitle('Noticia no encontrada | Municipalidad de Ensenada');
        this.cdr.markForCheck();
      },
    });
  }

  get galeria(): string[] {
    if (!this.noticia) return [];
    return [...new Set([this.noticia.imagen, ...(this.noticia.imagenes ?? [])])].filter(Boolean);
  }

  mostrarImagen(url: string): void {
    if (this.galeria.includes(url)) this.imagenActiva = url;
  }

  private actualizarSeo(noticia: Noticia): void {
    const origin = this.document.defaultView?.location.origin ?? '';
    const canonicalUrl = origin ? `${origin}/noticias/${noticia.slug}` : `/noticias/${noticia.slug}`;
    const imageUrl = origin ? new URL(noticia.imagen, origin).href : noticia.imagen;

    this.title.setTitle(`${noticia.titulo} | Municipalidad de Ensenada`);
    this.meta.updateTag({ name: 'description', content: noticia.bajada });
    this.meta.updateTag({ property: 'og:title', content: noticia.titulo });
    this.meta.updateTag({ property: 'og:description', content: noticia.bajada });
    this.meta.updateTag({ property: 'og:type', content: 'article' });
    this.meta.updateTag({ property: 'og:url', content: canonicalUrl });
    this.meta.updateTag({ property: 'og:image', content: imageUrl });
    this.meta.updateTag({ property: 'article:published_time', content: noticia.fechaPublicacion });
    this.meta.updateTag({ name: 'twitter:card', content: 'summary_large_image' });
    this.meta.updateTag({ name: 'twitter:title', content: noticia.titulo });
    this.meta.updateTag({ name: 'twitter:description', content: noticia.bajada });
    this.meta.updateTag({ name: 'twitter:image', content: imageUrl });

    let canonical = this.document.head.querySelector<HTMLLinkElement>('link[rel="canonical"]');
    if (!canonical) {
      canonical = this.document.createElement('link');
      canonical.rel = 'canonical';
      this.document.head.appendChild(canonical);
    }
    canonical.href = canonicalUrl;
  }

  private normalizarUrl(noticia: Noticia): void {
    if (!noticia.slug || this.identificador === noticia.slug) return;
    this.document.defaultView?.history.replaceState({}, '', `/noticias/${noticia.slug}`);
  }
}
