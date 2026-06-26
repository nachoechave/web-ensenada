import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Noticia } from '../../../models/noticia.model';
import { NoticiasService } from '../../../services/noticias.service';

@Component({
  selector: 'app-admin-noticias',
  imports: [RouterLink],
  templateUrl: './admin-noticias.html',
  styleUrl: './admin-noticias.css',
})
export class AdminNoticias {
  private readonly noticiasService = inject(NoticiasService);

  noticias: Noticia[] = this.noticiasService.obtenerTodas();

  eliminarNoticia(id: number): void {
    const confirmar = confirm('¿Seguro que querés eliminar esta noticia?');

    if (!confirmar) {
      return;
    }

    this.noticiasService.eliminar(id);
    this.noticias = this.noticiasService.obtenerTodas();
  }
}