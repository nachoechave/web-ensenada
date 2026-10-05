import { Component } from '@angular/core';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { secretariasMunicipales } from '../../config/secretarias-municipales';

@Component({
  selector: 'app-areas',
  imports: [Navbar, Footer],
  templateUrl: './areas.html',
  styleUrl: './areas.css',
})
export class Areas {
  readonly secretarias = secretariasMunicipales;
}
