import { Component, DestroyRef, afterNextRender, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { PortalContent } from '../../models/portal-content.model';
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

  contenido: PortalContent | null = null;
  cargando = true;
  errorCarga = '';

  constructor() {
    afterNextRender(() => {
      this.portalContentService
        .obtenerPublico(true)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: (contenido) => {
            this.contenido = contenido;
            this.cargando = false;
            this.errorCarga = '';
          },
          error: () => {
            this.contenido = null;
            this.cargando = false;
            this.errorCarga =
              'No se pudo cargar la información pública actualizada. Revisá que el backend esté disponible.';
          },
        });
    });
  }

  get secretarias() {
    return this.contenido?.areas.secretarias ?? [];
  }
}
