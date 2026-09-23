import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { NoticiasService } from '../../../services/noticias.service';
import { AuthService } from '../../../services/auth.service';
import { HaciendaService } from '../../../services/hacienda.service';
import { PublicacionHacienda } from '../../../models/hacienda.model';
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
  readonly auth = inject(AuthService);
  publicaciones = signal<PublicacionHacienda[]>([]);
  constructor() {
    const noticiasService = inject(NoticiasService);
    const haciendaService = inject(HaciendaService);
    if (this.auth.tieneRol(['HACIENDA']))
      haciendaService
        .listar(false)
        .subscribe({
          next: (p) => this.publicaciones.set(p),
          error: () => this.error.set('No se pudo cargar Hacienda.'),
        });
    if (this.auth.tieneRol(['PRENSA']))
      noticiasService.obtenerTodasDesdeApi().subscribe({
        next: (n) => this.noticias.set(n),
        error: () => this.error.set('No se pudo cargar el resumen.'),
      });
  }
  contar(estado: string) {
    return this.noticias().filter((n) => n.estado === estado).length;
  }
}
