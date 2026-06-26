import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';

import { Noticia } from '../../../models/noticia.model';
import { NoticiasService } from '../../../services/noticias.service';

type NoticiaForm = Omit<Noticia, 'id'>;

@Component({
  selector: 'app-admin-noticia-form',
  imports: [FormsModule, RouterLink],
  templateUrl: './admin-noticia-form.html',
  styleUrl: './admin-noticia-form.css',
})
export class AdminNoticiaForm {
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly noticiasService = inject(NoticiasService);

  noticiaId = Number(this.route.snapshot.paramMap.get('id'));
  esEdicion = !!this.noticiaId;

  imagenPreview = '';

  categorias = ['Obras públicas', 'Cultura', 'Deportes', 'Salud', 'Educación', 'Institucional'];

  noticia: NoticiaForm = {
    titulo: '',
    bajada: '',
    contenido: '',
    imagen: '',
    categoria: 'Institucional',
    fecha: new Date().toISOString().slice(0, 10),
    estado: 'Borrador',
  };

  constructor() {
    if (this.esEdicion) {
      const noticiaEncontrada = this.noticiasService.obtenerPorId(this.noticiaId);

      if (!noticiaEncontrada) {
        alert('La noticia no existe.');
        this.router.navigate(['/admin/noticias']);
        return;
      }

      const { id, ...noticiaSinId } = noticiaEncontrada;

      this.noticia = noticiaSinId;
      this.imagenPreview = this.noticia.imagen;
    }
  }

  guardarNoticia(): void {
    if (this.esEdicion) {
      this.noticiasService.actualizar(this.noticiaId, this.noticia);
      alert('Noticia actualizada correctamente.');
    } else {
      this.noticiasService.crear(this.noticia);
      alert('Noticia creada correctamente.');
    }

    this.router.navigate(['/admin/noticias']);
  }

  seleccionarImagen(event: Event): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];

    if (!archivo) {
      return;
    }

    if (!archivo.type.startsWith('image/')) {
      alert('El archivo seleccionado debe ser una imagen.');
      input.value = '';
      return;
    }

    const lector = new FileReader();

    lector.onload = () => {
      const resultado = lector.result;

      if (typeof resultado !== 'string') {
        return;
      }

      this.imagenPreview = resultado;
      this.noticia.imagen = resultado;

      this.cdr.detectChanges();
    };

    lector.readAsDataURL(archivo);
  }
}