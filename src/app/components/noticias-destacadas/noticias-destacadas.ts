import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NoticiasService } from '../../services/noticias.service';
import { Noticia } from '../../models/noticia.model';

@Component({
  selector: 'app-noticias-destacadas',
  imports: [RouterLink],
  templateUrl: './noticias-destacadas.html',
  styleUrl: './noticias-destacadas.css',
})
export class NoticiasDestacadas {
  private readonly noticiasService = inject(NoticiasService);

  noticias: Noticia[] = this.noticiasService.obtenerNoticiasPublicadas();

  noticiaPrincipal: Noticia | undefined = this.noticias[0];

  noticiasSecundarias: Noticia[] = this.noticias.slice(1, 3);
}