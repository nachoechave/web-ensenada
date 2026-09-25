import { Component, DestroyRef, inject } from '@angular/core';
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
  styleUrls: ['./home.css', './home-hero-overrides.css'],
})
export class Home {
  private readonly portalContentService = inject(PortalContentService);
  private readonly destroyRef = inject(DestroyRef);

  contenido = cloneDefaultPortalContent();

  constructor() {
    this.portalContentService
      .obtenerPublico()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((contenido) => (this.contenido = contenido));
  }

  esInterno(url: string): boolean {
    return url.startsWith('/') && !url.startsWith('//');
  }
}
