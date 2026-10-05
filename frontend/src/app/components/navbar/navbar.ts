import { Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink, RouterLinkActive } from '@angular/router';

import { cloneDefaultPortalContent } from '../../config/site-content';
import { PortalContentService } from '../../services/portal-content.service';

@Component({
  selector: 'app-navbar',
  imports: [RouterLink, RouterLinkActive],
  templateUrl: './navbar.html',
  styleUrls: ['./navbar.css', './navbar-visual-overrides.css'],
})
export class Navbar {
  private readonly portalContentService = inject(PortalContentService);
  private readonly destroyRef = inject(DestroyRef);

  contenido = cloneDefaultPortalContent();
  menuAbierto = false;
  busquedaAbierta = false;

  constructor() {
    this.portalContentService
      .obtenerPublico()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (contenido) => (this.contenido = contenido),
        // La navegación sigue disponible si el CMS falla; la página informa el error.
        error: () => {},
      });
  }

  cerrarMenus(): void {
    this.menuAbierto = false;
    this.busquedaAbierta = false;
  }

  alternarMenu(): void {
    this.menuAbierto = !this.menuAbierto;
    this.busquedaAbierta = false;
  }

  alternarBusqueda(): void {
    this.busquedaAbierta = !this.busquedaAbierta;
    this.menuAbierto = false;
  }
}
