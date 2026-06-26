import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Noticia } from '../../../models/noticia.model';
import { NoticiasService } from '../../../services/noticias.service';

@Component({
  selector: 'app-admin-dashboard',
  imports: [RouterLink],
  templateUrl: './admin-dashboard.html',
  styleUrl: './admin-dashboard.css',
})
export class AdminDashboard {
  private readonly noticiasService = inject(NoticiasService);

  noticias: Noticia[] = this.noticiasService.obtenerTodas();

  totalNoticias = this.noticias.length;

  noticiasPublicadas = this.noticias.filter(
    (noticia) => noticia.estado === 'Publicada',
  ).length;

  noticiasBorrador = this.noticias.filter(
    (noticia) => noticia.estado === 'Borrador',
  ).length;

  ultimasNoticias = this.noticias.slice(0, 4);
}