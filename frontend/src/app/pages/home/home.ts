import { siteContent } from '../../config/site-content';
import { Component } from '@angular/core';

import { Navbar } from '../../components/navbar/navbar';
import { AccesosRapidos } from '../../components/accesos-rapidos/accesos-rapidos';
import { NoticiasDestacadas } from '../../components/noticias-destacadas/noticias-destacadas';
import { Footer } from '../../components/footer/footer';

@Component({
  selector: 'app-home',
  imports: [Navbar, AccesosRapidos, NoticiasDestacadas, Footer],
  templateUrl: './home.html',
  styleUrl: './home.css',
})
export class Home {
  readonly contenido = siteContent;
}
