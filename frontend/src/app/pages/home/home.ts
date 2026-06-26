import { Component } from '@angular/core';

import { Navbar } from '../../components/navbar/navbar';
import { AccesosRapidos } from '../../components/accesos-rapidos/accesos-rapidos';
import { NoticiasDestacadas } from '../../components/noticias-destacadas/noticias-destacadas';
import { AgendaMunicipal } from '../../components/agenda-municipal/agenda-municipal';
import { AreasMunicipales } from '../../components/areas-municipales/areas-municipales';
import { Footer } from '../../components/footer/footer';

@Component({
  selector: 'app-home',
  imports: [
    Navbar,
    AccesosRapidos,
    NoticiasDestacadas,
    AgendaMunicipal,
    AreasMunicipales,
    Footer,
  ],
  templateUrl: './home.html',
  styleUrl: './home.css',
})
export class Home {}