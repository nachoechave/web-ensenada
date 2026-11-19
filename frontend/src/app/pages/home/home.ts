import { afterNextRender, Component, DestroyRef, inject, signal } from '@angular/core';
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
  styleUrls: ['./home.css', './home-hero-overrides.css', './home-intendencia-overrides.css', './home-hibrido.css'],
})
export class Home {
  private readonly portalContentService = inject(PortalContentService);
  private readonly destroyRef = inject(DestroyRef);
  private carouselTimer?: ReturnType<typeof setInterval>;

  readonly contenido = signal(cloneDefaultPortalContent());
  readonly heroIndex = signal(0);

  constructor() {
    this.destroyRef.onDestroy(() => this.detenerCarousel());
    afterNextRender(() => this.iniciarCarousel());

    this.portalContentService
      .obtenerPublico(true)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((contenido) => {
        this.contenido.set(contenido);
        this.heroIndex.set(0);
        this.precargarHero();
        this.iniciarCarousel();
      });
  }

  esInterno(url: string): boolean {
    return url.startsWith('/') && !url.startsWith('//');
  }

  iconoAcceso(titulo: string, fallback: string): string {
    const nombre = titulo.toLocaleLowerCase('es').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (nombre.includes('proveedor')) return 'providers-check';
    if (nombre.includes('boletin')) return 'book';
    if (nombre.includes('factura')) return 'receipt';
    if (nombre.includes('mail') || nombre.includes('correo')) return 'mail';
    if (nombre.includes('veterinar') || nombre.includes('castracion')) return 'paw-heart';
    if (nombre.includes('licencia') || nombre.includes('conducir')) return 'car';
    if (nombre.includes('reclamo')) return 'message-alert';
    if (nombre.includes('habilitacion')) return 'clipboard-check';
    return fallback;
  }

  iconoSecretaria(id: string): string {
    const iconos: Record<string, string> = {
      'hacienda-produccion': 'building',
      'desarrollo-social': 'users',
      'salud-medio-ambiente': 'heart',
      'obras-publicas': 'hardhat',
      'servicios-publicos': 'building',
      'inspeccion-control-urbano': 'document',
      'seguridad-justicia': 'document',
      'gestion-publica': 'building',
      'secretaria-privada': 'building',
      gobierno: 'building',
    };
    return iconos[id] ?? 'building';
  }

  seleccionarHero(index: number): void {
    if (index < 0 || index >= this.contenido().hero.imagenes.length) return;
    this.heroIndex.set(index);
  }

  private iniciarCarousel(): void {
    this.detenerCarousel();
    if (this.contenido().hero.imagenes.length < 2) return;
    if (typeof window === 'undefined') return;
    // El CSS elimina las transiciones con movimiento reducido, sin detener la rotación.

    const intervalo = Math.max(3, this.contenido().hero.intervaloSegundos) * 1000;
    this.carouselTimer = setInterval(() => {
      this.heroIndex.update((actual) => (actual + 1) % this.contenido().hero.imagenes.length);
    }, intervalo);
  }

  private detenerCarousel(): void {
    if (!this.carouselTimer) return;
    clearInterval(this.carouselTimer);
    this.carouselTimer = undefined;
  }

  private precargarHero(): void {
    if (typeof Image === 'undefined') return;
    for (const url of this.contenido().hero.imagenes) {
      const image = new Image();
      image.src = url;
    }
  }
}
