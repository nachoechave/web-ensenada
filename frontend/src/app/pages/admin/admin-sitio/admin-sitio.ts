import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { cloneDefaultPortalContent } from '../../../config/site-content';
import { PortalAgendaItem, PortalLinkItem } from '../../../models/portal-content.model';
import { PortalContentService } from '../../../services/portal-content.service';

@Component({
  selector: 'app-admin-sitio',
  imports: [FormsModule],
  templateUrl: './admin-sitio.html',
  styleUrl: './admin-sitio.css',
})
export class AdminSitio {
  private readonly portalContentService = inject(PortalContentService);

  contenido = cloneDefaultPortalContent();
  cargando = true;
  guardando = false;
  subiendoImagen = false;
  mensaje = '';
  error = '';

  readonly iconos = [
    ['document', 'Documento'],
    ['coins', 'Tasas / monedas'],
    ['calendar', 'Calendario'],
    ['building', 'Edificio'],
    ['recycle', 'Ambiente'],
    ['users', 'Personas'],
    ['hardhat', 'Obras'],
    ['heart', 'Salud'],
    ['ball', 'Deportes'],
    ['culture', 'Cultura'],
  ];

  constructor() {
    this.cargar();
  }

  cargar(): void {
    this.cargando = true;
    this.error = '';
    this.portalContentService.obtenerAdmin().subscribe({
      next: (contenido) => {
        this.contenido = contenido;
        this.cargando = false;
      },
      error: () => {
        this.error = 'No se pudo cargar la configuración del sitio.';
        this.cargando = false;
      },
    });
  }

  guardar(): void {
    this.guardando = true;
    this.mensaje = '';
    this.error = '';
    this.portalContentService.guardar(this.contenido).subscribe({
      next: (contenido) => {
        this.contenido = contenido;
        this.guardando = false;
        this.mensaje = 'Cambios guardados. La home pública ya usa esta configuración.';
      },
      error: () => {
        this.guardando = false;
        this.error = 'No se pudieron guardar los cambios.';
      },
    });
  }

  restaurarBase(): void {
    if (!window.confirm('¿Restaurar en el formulario los valores base? Después deberás guardar.')) return;
    this.contenido = cloneDefaultPortalContent();
    this.mensaje = 'Valores base cargados en el formulario. Guardá para publicarlos.';
  }

  agregarAcceso(): void {
    this.contenido.accesos.push(this.nuevoLink('Nuevo acceso'));
  }

  agregarTramite(): void {
    this.contenido.tramites.items.push(this.nuevoLink('Nuevo trámite'));
  }

  agregarArea(): void {
    this.contenido.areas.items.push(this.nuevoLink('Nueva área'));
  }

  agregarEvento(): void {
    this.contenido.agenda.items.push({ dia: '01', mes: 'ENE', titulo: 'Nuevo evento', lugar: '', hora: '' });
  }

  eliminar<T>(items: T[], index: number): void {
    items.splice(index, 1);
  }

  mover<T>(items: T[], index: number, direccion: -1 | 1): void {
    const destino = index + direccion;
    if (destino < 0 || destino >= items.length) return;
    [items[index], items[destino]] = [items[destino], items[index]];
  }

  subirImagen(event: Event, destino: 'hero' | 'intendencia'): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;

    this.subiendoImagen = true;
    this.error = '';
    this.portalContentService.subirImagen(archivo).subscribe({
      next: (respuesta) => {
        if (destino === 'hero') this.contenido.hero.imagen = respuesta.url;
        else this.contenido.intendencia.imagen = respuesta.url;
        this.subiendoImagen = false;
        input.value = '';
      },
      error: () => {
        this.error = 'No se pudo subir la imagen. Usá JPG o PNG dentro del límite permitido.';
        this.subiendoImagen = false;
        input.value = '';
      },
    });
  }

  private nuevoLink(titulo: string): PortalLinkItem {
    return { titulo, descripcion: '', url: '/', icono: 'document' };
  }
}
