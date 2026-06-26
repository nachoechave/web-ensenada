import { Component, inject } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';

import { Noticia } from '../../models/noticia.model';
import { NoticiasService } from '../../services/noticias.service';

@Component({
  selector: 'app-noticia-detalle',
  imports: [RouterLink],
  templateUrl: './noticia-detalle.html',
  styleUrl: './noticia-detalle.css',
})
export class NoticiaDetalle {
  private readonly route = inject(ActivatedRoute);
  private readonly noticiasService = inject(NoticiasService);

  noticia?: Noticia;

  constructor() {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    this.noticia = this.noticiasService.obtenerNoticiaPorId(id);
  }
}