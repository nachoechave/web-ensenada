import { Component, Input } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Noticia } from '../../models/noticia.model';

@Component({
  selector: 'app-noticia-card',
  imports: [RouterLink],
  templateUrl: './noticia-card.html',
  styleUrl: './noticia-card.css',
})
export class NoticiaCard {
  @Input({ required: true }) noticia!: Noticia;
}
