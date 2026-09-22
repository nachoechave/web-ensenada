import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NoticiasService } from '../../../services/noticias.service';
import { Noticia } from '../../../models/noticia.model';
@Component({
  selector: 'app-admin-dashboard',
  imports: [RouterLink],
  templateUrl: './admin-dashboard.html',
  styleUrl: './admin-dashboard.css',
})
export class AdminDashboard {
  noticias = signal<Noticia[]>([]);
  error = signal('');
  constructor() {
    inject(NoticiasService)
      .obtenerTodasDesdeApi()
      .subscribe({
        next: (n) => this.noticias.set(n),
        error: () => this.error.set('No se pudo cargar el resumen.'),
      });
  }
  contar(estado: string) {
    return this.noticias().filter((n) => n.estado === estado).length;
  }
}
