import { environment } from '../../environments/environment';
import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, catchError, map, of, shareReplay, tap } from 'rxjs';

import { cloneDefaultPortalContent } from '../config/site-content';
import { PortalContent } from '../models/portal-content.model';

export interface PortalImageUploadResponse {
  url: string;
  filename: string;
  mime: string;
  size: number;
}

@Injectable({ providedIn: 'root' })
export class PortalContentService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = environment.apiUrl;
  private publicContent$?: Observable<PortalContent>;

  obtenerPublico(force = false): Observable<PortalContent> {
    if (!this.publicContent$ || force) {
      this.publicContent$ = this.http.get<Partial<PortalContent>>(`${this.apiUrl}/site-content`).pipe(
        map((contenido) => this.normalizar(contenido)),
        catchError(() => of(cloneDefaultPortalContent())),
        shareReplay({ bufferSize: 1, refCount: false }),
      );
    }
    return this.publicContent$;
  }

  obtenerAdmin(): Observable<PortalContent> {
    return this.http
      .get<Partial<PortalContent>>(`${this.apiUrl}/admin/site-content`)
      .pipe(map((contenido) => this.normalizar(contenido)));
  }

  guardar(contenido: PortalContent): Observable<PortalContent> {
    return this.http.put<PortalContent>(`${this.apiUrl}/admin/site-content`, contenido).pipe(
      map((respuesta) => this.normalizar(respuesta)),
      tap(() => {
        this.publicContent$ = undefined;
      }),
    );
  }

  subirImagen(archivo: File): Observable<PortalImageUploadResponse> {
    const formData = new FormData();
    formData.append('archivo', archivo);
    return this.http.post<PortalImageUploadResponse>(`${this.apiUrl}/admin/archivos/sitio`, formData);
  }

  private normalizar(contenido: Partial<PortalContent> | null | undefined): PortalContent {
    const base = cloneDefaultPortalContent();
    if (!contenido) return base;

    const combinado = {
      ...base,
      ...contenido,
      topbar: { ...base.topbar, ...contenido.topbar },
      navbar: { ...base.navbar, ...contenido.navbar },
      hero: { ...base.hero, ...contenido.hero },
      tramites: { ...base.tramites, ...contenido.tramites },
      noticias: { ...base.noticias, ...contenido.noticias },
      areas: { ...base.areas, ...contenido.areas },
      intendencia: { ...base.intendencia, ...contenido.intendencia },
      agenda: { ...base.agenda, ...contenido.agenda },
      footer: { ...base.footer, ...contenido.footer },
    } as PortalContent;

    const accesosGuardados = contenido.accesos ?? [];
    const accesosLegacy = ['Licencias', 'Ambiente', 'Desarrollo Social', 'Obras Públicas', 'Salud', 'Deportes'];
    const conservaAccesosLegacy =
      accesosGuardados.length === accesosLegacy.length &&
      accesosGuardados.every((item, index) => item.titulo === accesosLegacy[index]);

    combinado.accesos =
      accesosGuardados.length === 0 || conservaAccesosLegacy ? base.accesos : accesosGuardados;
    if (!contenido.accesosTitulo || contenido.accesosTitulo === 'Accesos rápidos') {
      combinado.accesosTitulo = base.accesosTitulo;
    }

    combinado.tramites.items = contenido.tramites?.items ?? base.tramites.items;
    combinado.areas.items = contenido.areas?.items ?? base.areas.items;
    combinado.agenda.items = contenido.agenda?.items ?? base.agenda.items;

    const legacyImage = combinado.hero.imagen || base.hero.imagen;
    combinado.hero.imagenes =
      contenido.hero?.imagenes?.filter((url) => typeof url === 'string' && url.trim().length > 0) ?? [];
    if (combinado.hero.imagenes.length === 0) combinado.hero.imagenes = [legacyImage];
    combinado.hero.imagen = combinado.hero.imagenes[0] ?? legacyImage;
    combinado.hero.intervaloSegundos = Math.min(
      15,
      Math.max(3, Number(combinado.hero.intervaloSegundos) || base.hero.intervaloSegundos),
    );

    return combinado;
  }
}
