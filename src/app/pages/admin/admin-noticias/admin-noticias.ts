import { Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NoticiasService } from '../../../services/noticias.service';
import { Noticia } from '../../../models/noticia.model';

@Component({
  selector: 'app-admin-noticias',
  imports: [RouterLink],
  templateUrl: './admin-noticias.html',
  styleUrl: './admin-noticias.css',
})
export class AdminNoticias {
  private readonly noticiasService = inject(NoticiasService);

  noticias: Noticia[] = this.noticiasService.obtenerNoticias();

  eliminarNoticia(id: number): void {
    const confirma = confirm('¿Seguro que querés eliminar esta noticia?');

    if (!confirma) {
      return;
    }

    this.noticiasService.eliminarNoticia(id);
    this.noticias = this.noticiasService.obtenerNoticias();
  }

  cambiarEstadoPublicacion(noticia: Noticia): void {
    this.noticiasService.actualizarNoticia(noticia.id, {
      publicada: !noticia.publicada,
    });

    this.noticias = this.noticiasService.obtenerNoticias();
  }
}