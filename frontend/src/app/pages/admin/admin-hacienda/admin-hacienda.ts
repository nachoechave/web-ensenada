import { Component, inject, signal, computed } from '@angular/core';
import { RouterLink } from '@angular/router';
import { HaciendaService, errorHacienda } from '../../../services/hacienda.service';
import { PublicacionHacienda, tiposHacienda } from '../../../models/hacienda.model';

@Component({
  selector: 'app-admin-hacienda',
  imports: [RouterLink],
  templateUrl: './admin-hacienda.html',
  styleUrl: './admin-hacienda.css',
})
export class AdminHacienda {
  private readonly service = inject(HaciendaService);
  readonly tipos = tiposHacienda;
  publicaciones = signal<PublicacionHacienda[]>([]);
  filtro = signal('TODAS');
  error = signal('');
  cargando = signal(true);
  ocupado = signal(false);
  filtradas = computed(() =>
    this.publicaciones().filter((p) => this.filtro() === 'TODAS' || p.estado === this.filtro()),
  );
  constructor() {
    this.cargar();
  }
  cargar() {
    this.cargando.set(true);
    this.service.listar(false).subscribe({
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
  cambiarEstado(p: PublicacionHacienda) {
    if (this.ocupado()) return;
    this.error.set('');
    this.ocupado.set(true);
    this.service
      .editar(p.id, { ...p, estado: p.estado === 'PUBLICADA' ? 'BORRADOR' : 'PUBLICADA' })
      .subscribe({
        next: () => {
          this.ocupado.set(false);
          this.cargar();
        },
        error: (e) => {
          this.ocupado.set(false);
          this.error.set(errorHacienda(e));
        },
      });
  }
  archivar(p: PublicacionHacienda) {
    if (
      this.ocupado() ||
      !confirm('¿Archivar esta publicación? Dejará de estar disponible públicamente.')
    )
      return;
    this.error.set('');
    this.ocupado.set(true);
    this.service.archivar(p.id).subscribe({
      next: () => {
        this.ocupado.set(false);
        this.cargar();
      },
      error: (e) => {
        this.ocupado.set(false);
        this.error.set(errorHacienda(e));
      },
    });
  }
}
