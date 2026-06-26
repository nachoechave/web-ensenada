import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Noticia } from '../../models/noticia.model';
import { NoticiasService } from '../../services/noticias.service';

@Component({
  selector: 'app-noticias-destacadas',
  imports: [RouterLink],
  templateUrl: './noticias-destacadas.html',
  styleUrl: './noticias-destacadas.css',
})
export class NoticiasDestacadas {
  private readonly noticiasService = inject(NoticiasService);

  noticias: Noticia[] = this.noticiasService.obtenerPublicadas();

  noticiaPrincipal: Noticia | undefined = this.noticias[0];

  noticiasSecundarias: Noticia[] = this.noticias.slice(1, 3);
}