import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { makeStateKey, PLATFORM_ID, TransferState } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { cloneDefaultPortalContent } from '../config/site-content';
import { PortalContent } from '../models/portal-content.model';
import { PortalContentService } from './portal-content.service';

describe('PortalContentService: contenido público actual', () => {
  const estado = makeStateKey<PortalContent>('portal-content');
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

  it('transfiere el contenido público que se renderizó en el servidor', () => {
    TestBed.overrideProvider(PLATFORM_ID, { useValue: 'server' });
    http = TestBed.inject(HttpTestingController);
    service = TestBed.inject(PortalContentService);
    service.obtenerPublico().subscribe();
    http.expectOne('/api/site-content').flush({ topbar: { direccion: 'Sede renderizada' } });
    expect(TestBed.inject(TransferState).get(estado, cloneDefaultPortalContent()).topbar.direccion)
      .toBe('Sede renderizada');
  });

  it('hidrata con el contenido del servidor y actualiza al recibir una respuesta nueva', () => {
    TestBed.overrideProvider(PLATFORM_ID, { useValue: 'browser' });
    const transfer = TestBed.inject(TransferState);
    const inicial = cloneDefaultPortalContent();
    inicial.topbar.direccion = 'Sede renderizada';
    transfer.set(estado, inicial);
    http = TestBed.inject(HttpTestingController);
    service = TestBed.inject(PortalContentService);
    expect(service.contenidoPublico()?.topbar.direccion).toBe('Sede renderizada');
    expect(transfer.hasKey(estado)).toBe(false);

    service.obtenerPublico().subscribe();
    const request = http.expectOne('/api/site-content');
    expect(request.request.transferCache).toBe(false);
    request.flush({ topbar: { direccion: 'Sede actualizada' } });
    expect(service.contenidoPublico()?.topbar.direccion).toBe('Sede actualizada');
  });

  it('conserva el menú más reciente si una consulta anterior termina después de la nueva', () => {
    http = TestBed.inject(HttpTestingController);
    service = TestBed.inject(PortalContentService);
    service.obtenerPublico().subscribe();
    const anterior = http.expectOne('/api/site-content');
    service.obtenerPublico(true).subscribe();
    http.expectOne('/api/site-content').flush({ topbar: { direccion: 'Sede nueva' } });
    anterior.flush({ topbar: { direccion: 'Sede anterior' } });
    expect(service.contenidoPublico()?.topbar.direccion).toBe('Sede nueva');
  });
});
