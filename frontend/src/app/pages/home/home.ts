import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { AccesosRapidos } from '../../components/accesos-rapidos/accesos-rapidos';
import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';
import { NoticiasDestacadas } from '../../components/noticias-destacadas/noticias-destacadas';
import { externalLinks } from '../../config/external-links';
import { siteContent } from '../../config/site-content';

@Component({
  selector: 'app-home',
  imports: [RouterLink, Navbar, AccesosRapidos, NoticiasDestacadas, Footer],
  templateUrl: './home.html',
  styleUrl: './home.css',
})
export class Home {
  readonly contenido = siteContent;
  readonly externalLinks = externalLinks;
}
