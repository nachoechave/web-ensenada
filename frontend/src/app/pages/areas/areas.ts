import { Component, DestroyRef, inject, signal } from '@angular/core';
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

  readonly contenido = signal<PortalContent | null>(null);
  readonly cargando = signal(true);
  readonly errorCarga = signal('');

  constructor() {
    this.portalContentService
      .obtenerPublico(true)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (contenido) => {
          this.contenido.set(contenido);
          this.cargando.set(false);
          this.errorCarga.set('');
        },
        error: () => {
          this.contenido.set(null);
          this.cargando.set(false);
          this.errorCarga.set(
            'No se pudo cargar la información pública actualizada. Revisá que el backend esté disponible.',
          );
        },
      });
  }

  get secretarias() {
    return this.contenido()?.areas.secretarias ?? [];
  }
}
