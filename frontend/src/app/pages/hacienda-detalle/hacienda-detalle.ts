import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { Title, Meta } from '@angular/platform-browser';
import { toSignal, takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { switchMap, catchError, of, tap } from 'rxjs';
import { Navbar } from '../../components/navbar/navbar';
import { Footer } from '../../components/footer/footer';
import { HaciendaService, errorHacienda } from '../../services/hacienda.service';
import { tiposHacienda } from '../../models/hacienda.model';

@Component({
  selector: 'app-hacienda-detalle',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './hacienda-detalle.html',
  styleUrl: './hacienda-detalle.css',
})
export class HaciendaDetalle {
  private readonly route = inject(ActivatedRoute);
  private readonly service = inject(HaciendaService);
  private readonly title = inject(Title);
  private readonly meta = inject(Meta);
  readonly tipos = tiposHacienda;
  cargando = signal(true);
  error = signal('');
  readonly publicacion = toSignal(
    this.route.paramMap.pipe(
      tap(() => {
        this.cargando.set(true);
        this.error.set('');
      }),
      switchMap((params) =>
        this.service.obtener(Number(params.get('id'))).pipe(
          tap((p) => {
            this.cargando.set(false);
            this.title.setTitle(`${p.titulo} | Hacienda | Municipalidad de Ensenada`);
            this.meta.updateTag({ name: 'description', content: p.descripcion || p.titulo });
          }),
          catchError((e) => {
            this.cargando.set(false);
            this.error.set(errorHacienda(e));
            this.title.setTitle('Publicación no disponible | Hacienda');
            return of(null);
          }),
        ),
      ),
      takeUntilDestroyed(),
    ),
    { initialValue: null },
  );
}
