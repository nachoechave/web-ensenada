import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { cloneDefaultPortalContent } from '../../../config/site-content';
import { AdminSitio } from './admin-sitio';

describe('AdminSitio CMS', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
      ],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('envía dirección y teléfono editados y verifica lo persistido', () => {
    const fixture = TestBed.createComponent(AdminSitio);
    const inicial = cloneDefaultPortalContent();

    http.expectOne('/api/admin/site-content').flush(inicial);
    fixture.detectChanges();

    const direccionOriginal = inicial.areas.secretarias[0].direccion;
    const inputs = Array.from(
      fixture.nativeElement.querySelectorAll('input'),
    ) as HTMLInputElement[];
    const direccionInput = inputs.find((input) => input.value === direccionOriginal);

    expect(direccionInput).toBeTruthy();

    direccionInput!.value = 'Calle Nueva 123';
    direccionInput!.dispatchEvent(new Event('input'));
    fixture.detectChanges();

    expect(fixture.componentInstance.contenido.areas.secretarias[0].direccion).toBe(
      'Calle Nueva 123',
    );

    fixture.componentInstance.actualizarCampoSecretaria(
      0,
      'telefono',
      '(221) 555-9999',
    );
    fixture.componentInstance.guardar();

    const put = http.expectOne('/api/admin/site-content');
    expect(put.request.method).toBe('PUT');
    expect(put.request.body.areas.secretarias[0].direccion).toBe('Calle Nueva 123');
    expect(put.request.body.areas.secretarias[0].telefono).toBe('(221) 555-9999');
    put.flush(put.request.body);

    const getPersistido = http.expectOne('/api/admin/site-content');
    getPersistido.flush(put.request.body);

    expect(fixture.componentInstance.mensaje).toContain('verificados');
    expect(fixture.componentInstance.error).toBe('');
  });
});
