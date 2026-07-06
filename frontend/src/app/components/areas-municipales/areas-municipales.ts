import { Component, inject } from '@angular/core';

import { AreaMunicipal } from '../../models/area-municipal.model';
import { AreasService } from '../../services/areas.service';

@Component({
  selector: 'app-areas-municipales',
  imports: [],
  templateUrl: './areas-municipales.html',
  styleUrl: './areas-municipales.css',
})
export class AreasMunicipales {
  private readonly areasService = inject(AreasService);

  areas: AreaMunicipal[] = this.areasService.obtenerAreas();
}
