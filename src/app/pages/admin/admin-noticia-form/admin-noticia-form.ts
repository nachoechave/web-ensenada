import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';

interface NoticiaForm {
  titulo: string;
  bajada: string;
  contenido: string;
  imagen: string;
  categoria: string;
  fecha: string;
  estado: 'Publicada' | 'Borrador';
}

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

  noticiaId = this.route.snapshot.paramMap.get('id');
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
      this.noticia = {
        titulo: 'El municipio avanza con nuevas obras en los barrios',
        bajada: 'Se realizaron trabajos de infraestructura en distintos puntos de la ciudad.',
        contenido:
          'El Municipio de Ensenada continúa desarrollando obras públicas orientadas a mejorar la calidad de vida de los vecinos y vecinas.',
        imagen: '/assets/ensenada-hero.jpg',
        categoria: 'Obras públicas',
        fecha: '2026-06-25',
        estado: 'Publicada',
      };

      this.imagenPreview = this.noticia.imagen;
    }
  }

  guardarNoticia(): void {
    console.log('Noticia guardada:', this.noticia);

    alert(
      this.esEdicion
        ? 'Noticia actualizada correctamente.'
        : 'Noticia creada correctamente.',
    );

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