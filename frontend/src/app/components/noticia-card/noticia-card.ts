import { Component, Input } from '@angular/core';
import { DatePipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Noticia } from '../../models/noticia.model';

@Component({
  selector: 'app-noticia-card',
  imports: [RouterLink, DatePipe],
  templateUrl: './noticia-card.html',
  styleUrl: './noticia-card.css',
})
export class NoticiaCard {
  @Input({ required: true }) noticia!: Noticia;
}
