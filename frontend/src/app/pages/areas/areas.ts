import { Component, inject } from '@angular/core';
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

  areas: AreaMunicipal[] = this.areasService.obtenerAreas();
}
