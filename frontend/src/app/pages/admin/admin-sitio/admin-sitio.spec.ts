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

    const secretariaId = inicial.areas.secretarias[0].id;
    const direccionInput = fixture.nativeElement.querySelector(
      `input[data-field="direccion"][data-secretaria-id="${secretariaId}"]`,
    ) as HTMLInputElement | null;

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
  it('permite agregar, reordenar y publicar teléfonos útiles', () => {
    const fixture = TestBed.createComponent(AdminSitio);
    http.expectOne('/api/admin/site-content').flush(cloneDefaultPortalContent());
    fixture.detectChanges();

    const admin = fixture.componentInstance;
    admin.agregarTelefonoUtil();
    const nuevo = admin.contenido.telefonosUtiles.items.at(-1)!;
    nuevo.nombre = 'Guardia';
    nuevo.numero = '+54 221 555-1234';
    nuevo.descripcion = 'Atención';
    admin.mover(admin.contenido.telefonosUtiles.items, admin.contenido.telefonosUtiles.items.length - 1, 0);
    // Reordenamiento válido hacia arriba
    admin.mover(admin.contenido.telefonosUtiles.items, admin.contenido.telefonosUtiles.items.length - 1, -1);
    admin.guardar();
    const put = http.expectOne('/api/admin/site-content');
    expect(put.request.body.telefonosUtiles.items.some((item: { nombre: string }) => item.nombre === 'Guardia')).toBe(true);
    put.flush(put.request.body);
    http.expectOne('/api/admin/site-content').flush(put.request.body);
    expect(admin.error).toBe('');
  });

});
