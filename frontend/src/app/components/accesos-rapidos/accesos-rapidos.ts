import { RouterLink } from '@angular/router';
import { Component } from '@angular/core';
import { municipalLinks } from '../../config/external-links';
@Component({
  selector: 'app-accesos-rapidos',
  imports: [RouterLink],
  templateUrl: './accesos-rapidos.html',
  styleUrl: './accesos-rapidos.css',
})
export class AccesosRapidos {
  readonly accesos = municipalLinks;
}
