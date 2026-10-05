import { Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { cloneDefaultPortalContent } from '../../config/site-content';
import { PortalContentService } from '../../services/portal-content.service';

@Component({
  selector: 'app-areas',
  imports: [Navbar, Footer],
  templateUrl: './areas.html',
  styleUrl: './areas.css',
})
export class Areas {
  private readonly portalContentService = inject(PortalContentService);
  private readonly destroyRef = inject(DestroyRef);

  contenido = cloneDefaultPortalContent();
  errorCarga = '';

  constructor() {
    this.portalContentService
      .obtenerPublico(true)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (contenido) => {
          this.contenido = contenido;
          this.errorCarga = '';
        },
        error: () => {
          this.errorCarga =
            'No se pudo cargar la información pública actualizada. Revisá que el backend esté disponible.';
        },
      });
  }

  get secretarias() {
    return this.contenido.areas.secretarias;
  }
}
