import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { AdminNoticiaForm } from './admin-noticia-form';

describe('Imágenes de Noticias compatibles con Laravel', () => {
  let http: HttpTestingController;
  beforeEach(() => {
    TestBed.configureTestingModule({providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])]});
    http = TestBed.inject(HttpTestingController);
  });
  afterEach(() => http.verify());
  function file(type: string, size: number): File {
    const value = new File(['synthetic'], 'original', {type});
    Object.defineProperty(value, 'size', {value: size});
    return value;
  }
  function select(component: AdminNoticiaForm, files: File[]) {
    component.seleccionarImagen({target: {files, value: 'original'}} as unknown as Event);
  }
  for (const type of ['image/jpeg', 'image/png', 'image/webp']) {
    it(`acepta ${type} de 30 MiB y conserva la portada`, () => {
      const component = TestBed.createComponent(AdminNoticiaForm).componentInstance;
      select(component, [file(type, 30 * 1024 * 1024)]);
      const request = http.expectOne('/api/admin/archivos/noticias');
      expect(request.request.method).toBe('POST');
      request.flush({url: '/uploads/noticias/optimized.webp', contentType: 'image/webp', size: 100});
      expect(component.errorImagen).toBe('');
      expect(component.noticia.imagen).toBe('/uploads/noticias/optimized.webp');
      expect(component.imagenes).toEqual(['/uploads/noticias/optimized.webp']);
    });
  }
  it('rechaza más de 30 MiB antes de transmitir', () => {
    const component = TestBed.createComponent(AdminNoticiaForm).componentInstance;
    select(component, [file('image/jpeg', 30 * 1024 * 1024 + 1)]);
    expect(component.errorImagen).toContain('30 MB');
    expect(component.subiendoImagen).toBe(false);
  });
  for (const type of ['image/svg+xml', 'image/heic']) {
    it(`rechaza ${type} sin decodificador autorizado`, () => {
      const component = TestBed.createComponent(AdminNoticiaForm).componentInstance;
      select(component, [file(type, 100)]);
      expect(component.errorImagen).toContain('JPEG, PNG o WebP');
    });
  }
  it('mantiene el máximo de seis fotografías', () => {
    const component = TestBed.createComponent(AdminNoticiaForm).componentInstance;
    component.imagenes = Array.from({length: 6}, (_, i) => `/uploads/noticias/${i}.webp`);
    select(component, [file('image/webp', 100)]);
    expect(component.errorImagen).toContain('6 imágenes');
  });
  it('informa el límite correcto ante un 413 de PHP', () => {
    const component = TestBed.createComponent(AdminNoticiaForm).componentInstance;
    select(component, [file('image/jpeg', 100)]);
    http.expectOne('/api/admin/archivos/noticias').flush({}, {status: 413, statusText: 'Too Large'});
    expect(component.errorImagen).toContain('30 MB');
  });
});
