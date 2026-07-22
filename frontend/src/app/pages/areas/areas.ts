import { ChangeDetectorRef, Component, DestroyRef, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { AreaMunicipal } from '../../models/area-municipal.model';
import { AreasService } from '../../services/areas.service';

@Component({
  selector: 'app-areas',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './areas.html',
  styleUrl: './areas.css',
})
export class Areas {
  private readonly areasService = inject(AreasService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  areas: AreaMunicipal[] = [];

  constructor() {
    this.cargarAreas();

    this.areasService.cambiosAreas$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.cargarAreas());
  }

  private cargarAreas(): void {
    this.areasService.obtenerAreasDesdeApi().subscribe({
      next: (areas) => {
        this.areas = areas;
        this.cdr.detectChanges();
      },
      error: () => {
        this.areas = this.areasService.obtenerAreas();
        this.cdr.detectChanges();
      },
    });
  }
}
