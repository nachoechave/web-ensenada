import { Component, DestroyRef, computed, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';

import { cloneDefaultPortalContent } from '../../config/site-content';
import { PortalContentService } from '../../services/portal-content.service';

@Component({
  selector: 'app-navbar',
  imports: [RouterLink],
  templateUrl: './navbar.html',
  styleUrls: ['./navbar.css', './navbar-visual-overrides.css'],
})
export class Navbar {
  private readonly portalContentService = inject(PortalContentService);
  private readonly destroyRef = inject(DestroyRef);

  private readonly contenidoBase = cloneDefaultPortalContent();
  readonly contenido = computed(() => this.portalContentService.contenidoPublico() ?? this.contenidoBase);
  menuAbierto = false;

  constructor() {
    this.portalContentService
      .obtenerPublico()
      .pipe(takeUntilDestroyed(this.destroyRef))
      // Conservar el último menú cargado si falla la consulta.
      .subscribe({ error: () => {} });
  }

  cerrarMenu(): void {
    this.menuAbierto = false;
  }

  alternarMenu(): void {
    this.menuAbierto = !this.menuAbierto;
  }
}
