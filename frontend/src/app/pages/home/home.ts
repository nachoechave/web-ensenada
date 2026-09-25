import { Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { NoticiasDestacadas } from '../../components/noticias-destacadas/noticias-destacadas';
import { PortalIcon } from '../../components/portal-icon/portal-icon';
import { cloneDefaultPortalContent } from '../../config/site-content';
import { PortalContentService } from '../../services/portal-content.service';

@Component({
  selector: 'app-home',
  imports: [RouterLink, Navbar, NoticiasDestacadas, Footer, PortalIcon],
  templateUrl: './home.html',
  styleUrls: ['./home.css', './home-hero-overrides.css', './home-intendencia-overrides.css'],
})
export class Home {
  private readonly portalContentService = inject(PortalContentService);
  private readonly destroyRef = inject(DestroyRef);
  private carouselTimer?: ReturnType<typeof setInterval>;
  private pausado = false;

  contenido = cloneDefaultPortalContent();
  readonly heroIndex = signal(0);

  constructor() {
    this.destroyRef.onDestroy(() => this.detenerCarousel());

    this.portalContentService
      .obtenerPublico()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((contenido) => {
        this.contenido = contenido;
        this.heroIndex.set(0);
        this.precargarHero();
        this.iniciarCarousel();
      });
  }

  esInterno(url: string): boolean {
    return url.startsWith('/') && !url.startsWith('//');
  }

  pausarCarousel(): void {
    this.pausado = true;
  }

  reanudarCarousel(): void {
    this.pausado = false;
  }

  seleccionarHero(index: number): void {
    if (index < 0 || index >= this.contenido.hero.imagenes.length) return;
    this.heroIndex.set(index);
  }

  private iniciarCarousel(): void {
    this.detenerCarousel();
    if (this.contenido.hero.imagenes.length < 2) return;
    if (typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const intervalo = Math.max(3, this.contenido.hero.intervaloSegundos) * 1000;
    this.carouselTimer = setInterval(() => {
      if (this.pausado) return;
      this.heroIndex.update((actual) => (actual + 1) % this.contenido.hero.imagenes.length);
    }, intervalo);
  }

  private detenerCarousel(): void {
    if (!this.carouselTimer) return;
    clearInterval(this.carouselTimer);
    this.carouselTimer = undefined;
  }

  private precargarHero(): void {
    if (typeof Image === 'undefined') return;
    for (const url of this.contenido.hero.imagenes) {
      const image = new Image();
      image.src = url;
    }
  }
}
