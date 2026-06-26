import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Noticia } from '../../models/noticia.model';
import { NoticiasService } from '../../services/noticias.service';

@Component({
  selector: 'app-noticias',
  imports: [RouterLink],
  templateUrl: './noticias.html',
  styleUrl: './noticias.css',
})
export class Noticias {
  private readonly noticiasService = inject(NoticiasService);

  noticias: Noticia[] = this.noticiasService.obtenerPublicadas();
}