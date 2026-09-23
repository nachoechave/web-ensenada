import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Navbar } from '../../components/navbar/navbar';
import { Footer } from '../../components/footer/footer';
import { HaciendaService, errorHacienda } from '../../services/hacienda.service';
import { PublicacionHacienda, tiposHacienda } from '../../models/hacienda.model';

@Component({
  selector: 'app-hacienda',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './hacienda.html',
  styleUrl: './hacienda.css',
})
export class Hacienda {
  private readonly service = inject(HaciendaService);
  readonly tipos = tiposHacienda;
  publicaciones = signal<PublicacionHacienda[]>([]);
  cargando = signal(true);
  error = signal('');
  constructor() {
    this.cargar();
  }
  cargar() {
    this.cargando.set(true);
    this.error.set('');
    this.service.listar().subscribe({
      next: (p) => {
        this.publicaciones.set(p);
        this.cargando.set(false);
      },
      error: (e) => {
        this.error.set(errorHacienda(e));
        this.cargando.set(false);
      },
    });
  }
}
