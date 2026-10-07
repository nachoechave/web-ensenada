import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { vi } from 'vitest';
import { cloneDefaultPortalContent } from '../../config/site-content';
import { PortalContentService } from '../../services/portal-content.service';
import { AdminSitio } from '../admin/admin-sitio/admin-sitio';
import { Areas } from './areas';

const publicado = {
  areas: {
    secretarias: [{
      id: 'seguridad-justicia',
      nombre: 'Secretaría de Seguridad y Justicia',
      responsable: 'Martín Slobodian',
      direccion: 'La Merced y Don Bosco.',
      telefono: '(0221) 469-3154',
      horario: 'Lunes a viernes de 8:00 a 16:00',
      email: '',
    }],
  },
};

describe('Áreas con HTTP asíncrono y render zoneless', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideZonelessChangeDetection(), provideHttpClient(),
        provideHttpClientTesting(), provideRouter([])],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('sale de carga y renderiza el contenido publicado sin forzar detección de cambios', async () => {
    const fixture = TestBed.createComponent(Areas);
    await fixture.whenStable();
    expect(fixture.nativeElement.textContent).toContain('Cargando áreas municipales');

    const request = http.expectOne('/api/site-content');
    expect(request.request.transferCache).toBe(false);
    request.flush(publicado);
    // No detectChanges(): la respuesta HTTP debe notificar al render como en producción.
    await fixture.whenStable();

    const text = fixture.nativeElement.textContent;
    expect(text).toContain('La Merced y Don Bosco.');
    expect(text).toContain('(0221) 469-3154');
    expect(text).not.toContain('La ficha oficial no publica una sede general');
    expect(text).not.toContain('(0221) 469-3155');
    expect(text).not.toContain('Cargando áreas municipales');
  });

  it('muestra un error y finaliza la carga cuando falla HTTP', async () => {
    const fixture = TestBed.createComponent(Areas);
    await fixture.whenStable();
    http.expectOne('/api/site-content').flush({}, { status: 503, statusText: 'Unavailable' });
    await fixture.whenStable();
    expect(fixture.nativeElement.textContent).toContain('No pudimos cargar el directorio');
    expect(fixture.nativeElement.textContent).not.toContain('Cargando áreas municipales');
    expect(fixture.nativeElement.querySelectorAll('.secretaria-card')).toHaveLength(0);
  });

  it('finaliza la carga si el servidor no responde en ocho segundos', async () => {
    vi.useFakeTimers();
    try {
      const fixture = TestBed.createComponent(Areas);
      fixture.detectChanges();
      const request = http.expectOne('/api/site-content');
      await vi.advanceTimersByTimeAsync(8001);
      vi.useRealTimers();
      await fixture.whenStable();
      expect(request.cancelled).toBe(true);
      expect(fixture.nativeElement.textContent).toContain('No pudimos cargar el directorio');
      expect(fixture.nativeElement.textContent).not.toContain('Cargando áreas municipales');
    } finally {
      vi.useRealTimers();
    }
  });

  it.each([{}, { areas: { secretarias: [] } }])(
    'usa el directorio institucional base cuando el CMS público no tiene secretarías para %j',
    async (body) => {
      const fixture = TestBed.createComponent(Areas);
      await fixture.whenStable();
      http.expectOne('/api/site-content').flush(body);
      await fixture.whenStable();

      const secretariasBase = cloneDefaultPortalContent().areas.secretarias;
      expect(fixture.nativeElement.querySelectorAll('.secretaria-card')).toHaveLength(
        secretariasBase.length,
      );
      expect(fixture.nativeElement.textContent).toContain(secretariasBase[0].nombre);
      expect(fixture.nativeElement.textContent).not.toContain('Cargando áreas municipales');
    },
  );

  it('vuelve a consultar después de editar en AdminSitio sin reiniciar el servicio', async () => {
    const service = TestBed.inject(PortalContentService);
    const anterior = cloneDefaultPortalContent();
    const first = TestBed.createComponent(Areas);
    await first.whenStable();
    http.expectOne('/api/site-content').flush(anterior);
    await first.whenStable();
    first.destroy();

    const admin = TestBed.createComponent(AdminSitio);
    http.expectOne('/api/admin/site-content').flush(anterior);
    admin.componentInstance.contenido.areas.secretarias = publicado.areas.secretarias;
    admin.componentInstance.guardar();
    const put = http.expectOne('/api/admin/site-content');
    expect(put.request.method).toBe('PUT');
    expect(put.request.body.areas.secretarias).toEqual(publicado.areas.secretarias);
    put.flush(put.request.body);
    http.expectOne('/api/admin/site-content').flush(put.request.body);
    expect(admin.componentInstance.mensaje).toContain('verificados');
    admin.destroy();

    const second = TestBed.createComponent(Areas);
    await second.whenStable();
    http.expectOne('/api/site-content').flush(publicado);
    await second.whenStable();
    expect(TestBed.inject(PortalContentService)).toBe(service);
    expect(second.nativeElement.textContent).toContain('La Merced y Don Bosco.');
    expect(second.nativeElement.textContent).toContain('(0221) 469-3154');
    expect(second.nativeElement.textContent).not.toContain('(0221) 469-3155');
  });
});
