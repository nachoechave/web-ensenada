import { Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { cloneDefaultPortalContent } from '../../config/site-content';
import { PortalContentService } from '../../services/portal-content.service';

@Component({
  selector: 'app-intendencia',
  imports: [Navbar, Footer],
  templateUrl: './intendencia.html',
  styleUrl: './intendencia.css',
})
export class Intendencia {
  private readonly portalContentService = inject(PortalContentService);
  private readonly destroyRef = inject(DestroyRef);

  contenido = cloneDefaultPortalContent();

  constructor() {
    this.portalContentService
      .obtenerPublico()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((contenido) => (this.contenido = contenido));
  }
}
