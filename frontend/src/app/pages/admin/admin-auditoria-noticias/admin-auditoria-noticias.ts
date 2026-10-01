import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AuditoriaNoticia, NoticiasService } from '../../../services/noticias.service';

@Component({
  selector: 'app-admin-auditoria-noticias',
  imports: [RouterLink],
  templateUrl: './admin-auditoria-noticias.html',
  styleUrl: './admin-auditoria-noticias.css',
})
export class AdminAuditoriaNoticias {
  private readonly noticiasService = inject(NoticiasService);
  private readonly cdr = inject(ChangeDetectorRef);

  registros: AuditoriaNoticia[] = [];
  error = '';

  constructor() {
    this.noticiasService.obtenerAuditoriaDesdeApi().subscribe({
      next: (registros) => {
        this.registros = registros;
        this.error = '';
        this.cdr.detectChanges();
      },
      error: () => {
        this.registros = [];
        this.error = 'No se pudo cargar el historial editorial.';
        this.cdr.detectChanges();
      },
    });
  }
}
