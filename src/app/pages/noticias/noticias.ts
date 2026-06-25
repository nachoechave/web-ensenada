import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NoticiasService } from '../../services/noticias.service';
import { Noticia } from '../../models/noticia.model';

@Component({
  selector: 'app-noticias',
  imports: [RouterLink],
  templateUrl: './noticias.html',
  styleUrl: './noticias.css',
})
export class Noticias {
  private noticiasService = inject(NoticiasService);

  noticias: Noticia[] = this.noticiasService.obtenerNoticiasPublicadas();
}