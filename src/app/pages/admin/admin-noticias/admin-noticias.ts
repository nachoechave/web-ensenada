import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

interface AdminNoticia {
  id: number;
  titulo: string;
  categoria: string;
  fecha: string;
  estado: 'Publicada' | 'Borrador';
}

@Component({
  selector: 'app-admin-noticias',
  imports: [RouterLink],
  templateUrl: './admin-noticias.html',
  styleUrl: './admin-noticias.css',
})
export class AdminNoticias {
  noticias: AdminNoticia[] = [
    {
      id: 1,
      titulo: 'El municipio avanza con nuevas obras en los barrios',
      categoria: 'Obras públicas',
      fecha: '2026-06-25',
      estado: 'Publicada',
    },
    {
      id: 2,
      titulo: 'Nueva agenda de actividades culturales',
      categoria: 'Cultura',
      fecha: '2026-06-24',
      estado: 'Publicada',
    },
    {
      id: 3,
      titulo: 'Inscripción abierta a talleres deportivos',
      categoria: 'Deportes',
      fecha: '2026-06-23',
      estado: 'Borrador',
    },
  ];

  eliminarNoticia(id: number): void {
    const confirmar = confirm('¿Seguro que querés eliminar esta noticia?');

    if (!confirmar) {
      return;
    }

    this.noticias = this.noticias.filter((noticia) => noticia.id !== id);
  }
}