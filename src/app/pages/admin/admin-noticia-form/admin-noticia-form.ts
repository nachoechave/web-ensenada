import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { NoticiasService } from '../../../services/noticias.service';
import { Noticia } from '../../../models/noticia.model';
@Component({
  selector: 'app-admin-noticia-form',
  imports: [FormsModule, RouterLink],
  templateUrl: './admin-noticia-form.html',
  styleUrl: './admin-noticia-form.css',
})
export class AdminNoticiaForm {
  private readonly noticiasService = inject(NoticiasService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  noticiaId = Number(this.route.snapshot.paramMap.get('id'));
  modoEdicion = Boolean(this.noticiaId);

  noticia: Omit<Noticia, 'id'> = {
    titulo: '',
    bajada: '',
    contenido: '',
    imagenUrl: '',
    categoria: '',
    fechaPublicacion: new Date().toISOString().slice(0, 10),
    autor: 'Prensa Municipal',
    publicada: false,
  };

  constructor() {
    if (this.modoEdicion) {
      const noticiaEncontrada = this.noticiasService.obtenerNoticiaPorId(this.noticiaId);

      if (noticiaEncontrada) {
        const { id, ...datosNoticia } = noticiaEncontrada;
        this.noticia = datosNoticia;
      }
    }
  }

  guardarNoticia(): void {
    if (this.modoEdicion) {
      this.noticiasService.actualizarNoticia(this.noticiaId, this.noticia);
    } else {
      this.noticiasService.crearNoticia(this.noticia);
    }

    this.router.navigate(['/admin/noticias']);
  }

  seleccionarImagen(event: Event): void {
  const input = event.target as HTMLInputElement;

  if (!input.files || input.files.length === 0) {
    return;
  }

  const archivo = input.files[0];

  if (!archivo.type.startsWith('image/')) {
    alert('El archivo seleccionado debe ser una imagen.');
    input.value = '';
    return;
  }

  const lector = new FileReader();

  lector.onload = () => {
    this.noticia.imagenUrl = lector.result as string;
  };

  lector.readAsDataURL(archivo);
  }
}