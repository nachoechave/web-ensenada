import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { PLATFORM_ID } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { PortalContentService } from './portal-content.service';

describe('PortalContentService: contenido público actual', () => {
  let http: HttpTestingController;
  let service: PortalContentService;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
  });

  afterEach(() => http.verify());

  it.each(['browser', 'server'])('propaga fallos sin publicar defaults en %s', (platform) => {
    TestBed.overrideProvider(PLATFORM_ID, { useValue: platform });
    http = TestBed.inject(HttpTestingController);
    service = TestBed.inject(PortalContentService);
    let emitted = false;
    let status = 0;
    service.obtenerPublico().subscribe({
      next: () => { emitted = true; },
      error: (error) => { status = error.status; },
    });
    http.expectOne('/api/site-content').flush({}, { status: 503, statusText: 'Unavailable' });
    expect(emitted).toBe(false);
    expect(status).toBe(503);
  });

  it('una nueva visita forzada ignora el replay de una visita anterior', () => {
    http = TestBed.inject(HttpTestingController);
    service = TestBed.inject(PortalContentService);
    service.obtenerPublico().subscribe();
    http.expectOne('/api/site-content').flush({ areas: { secretarias: [] } });
    let title = '';
    service.obtenerPublico(true).subscribe((value) => { title = value.areas.paginaTitulo; });
    const request = http.expectOne('/api/site-content');
    expect(request.request.transferCache).toBe(false);
    request.flush({ areas: { paginaTitulo: 'Actualizado', secretarias: [] } });
    expect(title).toBe('Actualizado');
  });

  it('rechaza una respuesta nula en vez de publicar datos de ejemplo', () => {
    http = TestBed.inject(HttpTestingController);
    service = TestBed.inject(PortalContentService);
    let failed = false;
    service.obtenerPublico().subscribe({
      next: () => { throw new Error('No debe emitir contenido'); },
      error: () => { failed = true; },
    });
    http.expectOne('/api/site-content').flush(null);
    expect(failed).toBe(true);
  });
});
