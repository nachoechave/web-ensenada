import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { cloneDefaultPortalContent } from '../../config/site-content';
import { AdminSitio } from '../../pages/admin/admin-sitio/admin-sitio';
import { Hacienda } from '../../pages/hacienda/hacienda';
import { Noticias } from '../../pages/noticias/noticias';
import { Navbar } from './navbar';

const items = [
  { texto: 'Inicio', url: '/', visible: true },
  { texto: 'Áreas', url: '/areas', visible: true },
  { texto: 'Noticias', url: '/noticias', visible: true },
  { texto: 'Hacienda', url: '/hacienda', visible: true },
  { texto: 'Proveedores', url: '/proveedores', visible: true },
  { texto: 'Enlace oculto', url: '/oculto', visible: false },
];
const publicado = {
  navbar: { items },
  topbar: { telefono: '(221) 555-0190', direccion: 'Sede de prueba', horarios: 'Lunes de 8 a 12' },
  footer: { telefono: '(221) 555-0190', direccion: 'Sede de prueba', email: 'portal@example.test' },
};

function enlaces(root: HTMLElement): string[] {
  return Array.from(root.querySelectorAll('#main-navigation a:not(.boletin-button)'), (link) => link.textContent.trim());
}

describe('Navegación publicada del CMS', () => {
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideZonelessChangeDetection(), provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it.each([
    { component: Noticias, path: '/noticias', api: '/api/noticias', body: { content: [], page: 0, totalPages: 0, totalElements: 0 } },
    { component: Hacienda, path: '/hacienda', api: '/api/hacienda', body: [] },
  ])('actualiza menú y contacto en $path cuando el CMS responde después del primer render', async ({ component, api, body }) => {
    const fixture = TestBed.createComponent<Noticias | Hacienda>(component);
    const root: HTMLElement = fixture.nativeElement;
    await fixture.whenStable();
    expect(enlaces(root)).toContain('Municipio');
    http.expectOne((request) => request.url === api).flush(body);
    await fixture.whenStable();

    http.expectOne('/api/site-content').flush(publicado);
    await fixture.whenStable();

    expect(enlaces(root)).toEqual(['Inicio', 'Áreas', 'Noticias', 'Hacienda', 'Proveedores']);
    const topbar = root.querySelector('.topbar');
    expect(topbar?.textContent).toContain('Sede de prueba');
    expect(topbar?.textContent).toContain('Lunes de 8 a 12');
    expect(topbar?.querySelector('a')?.getAttribute('href')).toBe('tel:(221)555-0190');
    expect(root.querySelector('footer a[href="mailto:portal@example.test"]')).not.toBeNull();
    expect(root.querySelector('.search-toggle, .search-panel')).toBeNull();
  });

  it('conserva los enlaces base si el CMS falla y mantiene el control del menú móvil', async () => {
    const fixture = TestBed.createComponent(Navbar);
    const root: HTMLElement = fixture.nativeElement;
    await fixture.whenStable();
    http.expectOne('/api/site-content').flush(null, { status: 503, statusText: 'Unavailable' });
    await fixture.whenStable();
    expect(enlaces(root)).toContain('Municipio');

    const toggle = root.querySelector<HTMLButtonElement>('.menu-toggle')!;
    toggle.click();
    await fixture.whenStable();
    expect(toggle.getAttribute('aria-expanded')).toBe('true');
    expect(root.querySelector('#main-navigation')?.classList.contains('open')).toBe(true);
    root.querySelector<HTMLAnchorElement>('.brand')!.click();
    await fixture.whenStable();
    expect(toggle.getAttribute('aria-expanded')).toBe('false');
  });

  it('respeta un menú publicado vacío', async () => {
    const fixture = TestBed.createComponent(Navbar);
    await fixture.whenStable();
    http.expectOne('/api/site-content').flush({ navbar: { items: [] } });
    await fixture.whenStable();
    expect(enlaces(fixture.nativeElement)).toEqual([]);
    expect(fixture.nativeElement.querySelector('.boletin-button')).not.toBeNull();
  });

  it('muestra el nuevo orden y la visibilidad después de guardar desde AdminSitio', async () => {
    const primeraVisita = TestBed.createComponent(Navbar);
    http.expectOne('/api/site-content').flush(publicado);
    await primeraVisita.whenStable();
    primeraVisita.destroy();

    const editor = TestBed.createComponent(AdminSitio);
    const anterior = cloneDefaultPortalContent();
    anterior.navbar.items = items;
    http.expectOne('/api/admin/site-content').flush(anterior);
    const nuevos = [items[4], { ...items[2], visible: false }, ...items.slice(0, 2)];
    editor.componentInstance.contenido.navbar.items = nuevos;
    editor.componentInstance.guardar();
    const guardado = http.expectOne({ method: 'PUT', url: '/api/admin/site-content' });
    expect(guardado.request.body.navbar.items).toEqual(nuevos);
    guardado.flush(guardado.request.body);
    http.expectOne('/api/admin/site-content').flush(guardado.request.body);
    expect(editor.componentInstance.mensaje).toContain('verificados');

    const segundaVisita = TestBed.createComponent(Navbar);
    await segundaVisita.whenStable();
    http.expectOne('/api/site-content').flush(guardado.request.body);
    await segundaVisita.whenStable();
    expect(enlaces(segundaVisita.nativeElement)).toEqual(['Proveedores', 'Inicio', 'Áreas']);
  });
});
