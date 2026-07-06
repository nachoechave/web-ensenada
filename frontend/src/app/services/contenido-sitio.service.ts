import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { ContenidoSitio } from '../models/contenido-sitio.model';

@Injectable({
  providedIn: 'root',
})
export class ContenidoSitioService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8080/api';
  private readonly storageKey = 'municipio-contenido-sitio';

  private readonly contenidoInicial: ContenidoSitio = {
    heroTitulo: 'Construimos juntos la ciudad que sonamos',
    heroBajada:
      'Informacion, servicios y novedades para estar cerca de cada vecino y vecina de Ensenada.',
    footerDescripcion:
      'Un municipio cerca de cada vecino, trabajando por una ciudad mas integrada, moderna y participativa.',
    emailContacto: 'info@ensenada.gob.ar',
    telefonoContacto: '0221 460-0000',
    direccionMunicipio: 'Ensenada, Buenos Aires',
    horarioAtencion: 'Lunes a viernes de 8 a 14 hs',
    textoTramites: 'Orientacion sobre servicios, turnos y solicitudes municipales.',
    notaInstitucionalContacto:
      'Esta web reune informacion dinamica de noticias, agenda, servicios y canales de contacto municipales.',
  };

  obtenerContenidoDesdeApi(): Observable<ContenidoSitio> {
    return this.http.get<ContenidoSitio>(`${this.apiUrl}/contenido-sitio`);
  }

  guardarContenidoDesdeApi(contenido: ContenidoSitio): Observable<ContenidoSitio> {
    return this.http.put<ContenidoSitio>(`${this.apiUrl}/admin/contenido`, contenido);
  }

  obtenerContenido(): ContenidoSitio {
    const contenidoGuardado = localStorage.getItem(this.storageKey);

    if (!contenidoGuardado) {
      return this.contenidoInicial;
    }

    try {
      return {
        ...this.contenidoInicial,
        ...(JSON.parse(contenidoGuardado) as Partial<ContenidoSitio>),
      };
    } catch {
      return this.contenidoInicial;
    }
  }

  guardarContenido(contenido: ContenidoSitio): void {
    localStorage.setItem(this.storageKey, JSON.stringify(contenido));
  }

  obtenerContenidoInicial(): ContenidoSitio {
    return { ...this.contenidoInicial };
  }
}
