import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { cloneDefaultPortalContent } from '../../../config/site-content';
import { PortalLinkItem, PortalSecretariaItem } from '../../../models/portal-content.model';
import { PortalContentService } from '../../../services/portal-content.service';

@Component({
  selector: 'app-admin-sitio',
  imports: [FormsModule],
  templateUrl: './admin-sitio.html',
  styleUrl: './admin-sitio.css',
})
export class AdminSitio {
  private readonly portalContentService = inject(PortalContentService);
  private readonly cdr = inject(ChangeDetectorRef);

  contenido = cloneDefaultPortalContent();
  cargando = true;
  guardando = false;
  subiendoImagen = false;
  mensaje = '';
  error = '';
  nuevaImagenHero = '';

  readonly iconos = [
    ['document', 'Documento'],
    ['receipt', 'Recibo'],
    ['invoice', 'Factura'],
    ['mail', 'Correo'],
    ['providers', 'Proveedores'],
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
        this.cdr.markForCheck();
      },
      error: () => {
        this.error = 'No se pudo cargar la configuración del sitio.';
        this.cargando = false;
        this.cdr.markForCheck();
      },
    });
  }

  guardar(): void {
    this.guardando = true;
    this.mensaje = '';
    this.error = '';
    this.contenido.hero.intervaloSegundos = Math.min(
      15,
      Math.max(3, Number(this.contenido.hero.intervaloSegundos) || 4),
    );
    this.contenido.hero.imagen = this.contenido.hero.imagenes[0] ?? this.contenido.hero.imagen;

    const enviado = structuredClone(this.contenido);

    this.portalContentService.guardar(enviado).subscribe({
      next: (contenido) => {
        const enviadoJson = JSON.stringify(enviado);
        const persistidoJson = JSON.stringify(contenido);
        this.contenido = contenido;
        this.guardando = false;
        if (enviadoJson !== persistidoJson) {
          this.error = 'El servidor respondió, pero lo persistido no coincide con lo enviado. Revisá los cambios antes de continuar.';
          this.mensaje = '';
        } else {
          this.mensaje = 'Cambios guardados y verificados contra la base de datos.';
        }
        this.cdr.markForCheck();
      },
      error: () => {
        this.guardando = false;
        this.error = 'No se pudieron guardar los cambios.';
        this.cdr.markForCheck();
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

  agregarSecretaria(): void {
    this.contenido.areas.secretarias.push(this.nuevaSecretaria());
  }

  eliminarSecretaria(index: number): void {
    this.contenido.areas.secretarias.splice(index, 1);
  }

  actualizarCampoSecretaria(
    index: number,
    campo: Exclude<keyof PortalSecretariaItem, 'id'>,
    valor: string,
  ): void {
    const secretaria = this.contenido.areas.secretarias[index];
    if (!secretaria) return;
    this.contenido.areas.secretarias[index] = {
      ...secretaria,
      [campo]: valor,
    };
  }

  eliminarImagenIntendencia(index: number): void {
    this.contenido.intendencia.galeria.splice(index, 1);
  }

  agregarEvento(): void {
    this.contenido.agenda.items.push({ dia: '01', mes: 'ENE', titulo: 'Nuevo evento', lugar: '', hora: '' });
  }

  agregarImagenHeroUrl(): void {
    const url = this.nuevaImagenHero.trim();
    if (!url) return;
    this.contenido.hero.imagenes.push(url);
    if (this.contenido.hero.imagenes.length === 1) this.contenido.hero.imagen = url;
    this.nuevaImagenHero = '';
  }

  eliminarImagenHero(index: number): void {
    if (this.contenido.hero.imagenes.length <= 1) {
      this.error = 'El hero debe conservar al menos una imagen.';
      return;
    }
    this.contenido.hero.imagenes.splice(index, 1);
    this.contenido.hero.imagen = this.contenido.hero.imagenes[0];
  }

  moverImagenHero(index: number, direccion: -1 | 1): void {
    this.mover(this.contenido.hero.imagenes, index, direccion);
    this.contenido.hero.imagen = this.contenido.hero.imagenes[0];
  }

  eliminar<T>(items: T[], index: number): void {
    items.splice(index, 1);
  }

  mover<T>(items: T[], index: number, direccion: -1 | 1): void {
    const destino = index + direccion;
    if (destino < 0 || destino >= items.length) return;
    [items[index], items[destino]] = [items[destino], items[index]];
  }

  subirImagen(event: Event, destino: 'hero' | 'intendencia' | 'intendenciaGaleria'): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;

    this.subiendoImagen = true;
    this.error = '';
    this.portalContentService.subirImagen(archivo).subscribe({
      next: (respuesta) => {
        if (destino === 'hero') {
          this.contenido.hero.imagenes.push(respuesta.url);
          if (this.contenido.hero.imagenes.length === 1) this.contenido.hero.imagen = respuesta.url;
        } else if (destino === 'intendencia') {
          this.contenido.intendencia.imagen = respuesta.url;
        } else {
          this.contenido.intendencia.galeria.push(respuesta.url);
        }
        this.subiendoImagen = false;
        input.value = '';
        this.cdr.markForCheck();
      },
      error: (error: HttpErrorResponse) => {
        this.error = this.obtenerMensajeUpload(error);
        this.subiendoImagen = false;
        input.value = '';
        this.cdr.markForCheck();
      },
    });
  }

  private obtenerMensajeUpload(error: HttpErrorResponse): string {
    const detalle =
      typeof error.error === 'string'
        ? error.error
        : error.error?.detail || error.error?.message || error.error?.error;

    if (typeof detalle === 'string' && detalle.trim()) return detalle.trim();
    if (error.status === 413) return 'La imagen supera el límite de 30 MB.';
    return 'No se pudo procesar la imagen. Probá con JPG o PNG; el sistema la redimensiona y comprime automáticamente.';
  }

  private nuevaSecretaria(): PortalSecretariaItem {
    return {
      id: 'nueva-secretaria-' + (this.contenido.areas.secretarias.length + 1),
      nombre: 'Nueva secretaría',
      responsable: '',
      direccion: '',
      telefono: '',
      horario: '',
      email: '',
    };
  }

  private nuevoLink(titulo: string): PortalLinkItem {
    return { titulo, descripcion: '', url: '/', icono: 'document' };
  }
}
