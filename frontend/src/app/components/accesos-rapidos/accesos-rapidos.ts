import { Component } from '@angular/core';
import { municipalLinks } from '../../config/external-links';
@Component({
  selector: 'app-accesos-rapidos',
  templateUrl: './accesos-rapidos.html',
  styleUrl: './accesos-rapidos.css',
})
export class AccesosRapidos {
  readonly accesos = municipalLinks;
}
