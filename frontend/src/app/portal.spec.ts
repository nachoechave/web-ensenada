import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import {
  ActivatedRoute,
  ActivatedRouteSnapshot,
  provideRouter,
  Router,
  RouterStateSnapshot,
} from '@angular/router';
import { of } from 'rxjs';
import { vi } from 'vitest';
import { adminGuard } from './core/admin.guard';
import { roleGuard } from './core/role.guard';
import { AuthService } from './services/auth.service';
import { NoticiasService } from './services/noticias.service';
import { PortalContentService } from './services/portal-content.service';
import { cloneDefaultPortalContent } from './config/site-content';
import { AdminNoticias } from './pages/admin/admin-noticias/admin-noticias';
import { AdminNoticiaForm } from './pages/admin/admin-noticia-form/admin-noticia-form';
import { Noticias } from './pages/noticias/noticias';
import { NoticiasDestacadas } from './components/noticias-destacadas/noticias-destacadas';
import { Noticia } from './models/noticia.model';
import { AdminLayout } from './pages/admin/admin-layout/admin-layout';
import { routes } from './app.routes';

const news: Noticia = {
  id: 1,
  titulo: 'Obras en Ensenada',
  bajada: 'Resumen',
  contenido: 'Texto de la noticia',
  imagen: '/assets/ensenada-hero.jpg',
  categoria: 'Institucional',
  fechaPublicacion: '2026-09-22',
  estado: 'PUBLICADA',
  destacada: true,
};

describe('Portal de prensa', () => {
  it('las rutas conservan noticias y agregan el CMS del sitio para SuperAdmin', () => {
    expect(routes.map((route) => route.path)).toEqual([
      'hacienda/:id',
      'hacienda',
      'registro-proveedores',
      '',
      'noticias',
      'noticias/:id',
      'admin/login',
      'admin',
      '**',
    ]);
    const admin = routes.find((route) => route.path === 'admin')!;
    expect(admin.children?.map((route) => route.path)).toEqual([
      'hacienda',
      'hacienda/nueva',
      'hacienda/editar/:id',
      '',
      'sitio',
      'noticias',
      'noticias/nueva',
      'noticias/editar/:id',
      'usuarios',
      'mi-cuenta',
    ]);
    expect(admin.canActivateChild).toContain(adminGuard);
    expect(admin.children?.find((route) => route.path === 'sitio')?.data?.['roles']).toEqual([
      'SUPER_ADMIN',
    ]);
  });

  let http: HttpTestingController;
  let params: Map<string, string>;

  beforeEach(() => {
    localStorage.clear();
    params = new Map();
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: ActivatedRoute, useValue: { snapshot: { paramMap: params } } },
        {
          provide: PortalContentService,
          useValue: {
            obtenerPublico: () => of(cloneDefaultPortalContent()),
          },
        },
      ],
    });
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    localStorage.clear();
    vi.restoreAllMocks();
  });

  function session(roles: string[] = ['PRENSA']) {
    localStorage.setItem('admin-token', 'test');
    localStorage.setItem(
      'admin-user',
      JSON.stringify({ nombre: 'Prensa', email: 'prensa@example.test', roles }),
    );
  }

  it('el guard redirige sin sesión y permite una sesión válida', () => {
    const run = () =>
      TestBed.runInInjectionContext(() =>
        adminGuard({} as ActivatedRouteSnapshot, {} as RouterStateSnapshot),
      );
    expect(String(run())).toBe('/admin/login');
    session();
    expect(run()).toBe(true);
  });

  it('PRENSA no accede a usuarios; SUPER_ADMIN sí', () => {
    const run = () =>
      TestBed.runInInjectionContext(() =>
        roleGuard(
          { data: { roles: ['SUPER_ADMIN'] } } as unknown as ActivatedRouteSnapshot,
          {} as RouterStateSnapshot,
        ),
      );
    session();
    expect(String(run())).toBe('/admin');
    session(['SUPER_ADMIN']);
    expect(run()).toBe(true);
  });

  it('el menú del periodista contiene solo noticias y cuenta', () => {
    session();
    const fixture = TestBed.createComponent(AdminLayout);
    fixture.detectChanges();
    const text = fixture.nativeElement.querySelector('nav').textContent;
    expect(text).toContain('Noticias');
    expect(text).toContain('Mi cuenta');
    expect(text).not.toContain('Sitio público');
    expect(text).not.toContain('Usuarios y roles');
    expect(text).not.toContain('Hacienda');
  });

  it('login guarda únicamente la sesión recibida', () => {
    const auth = TestBed.inject(AuthService);
    auth.login({ email: 'p@example.test', password: 'password' }).subscribe();
    http
      .expectOne('/api/auth/login')
      .flush({ token: 'jwt', nombre: 'P', email: 'p@example.test', roles: ['PRENSA'] });
    expect(auth.obtenerToken()).toBe('jwt');
    expect(auth.tieneRol(['SUPER_ADMIN'])).toBe(false);
  });

  it('listado muestra API, miniaturas y filtros de estado', () => {
    const fixture = TestBed.createComponent(AdminNoticias);
    http
      .expectOne('/api/admin/noticias')
      .flush([news, { ...news, id: 2, estado: 'BORRADOR', destacada: false }]);
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelectorAll('tbody img').length).toBe(2);
    fixture.componentInstance.filtro = 'BORRADOR';
    fixture.detectChanges();
    expect(fixture.componentInstance.filtradas.map((n) => n.id)).toEqual([2]);
    fixture.componentInstance.filtro = 'Destacadas';
    expect(fixture.componentInstance.filtradas.map((n) => n.id)).toEqual([1]);
  });

  it('listado informa errores de API sin datos demo', () => {
    const fixture = TestBed.createComponent(AdminNoticias);
    http.expectOne('/api/admin/noticias').flush({}, { status: 500, statusText: 'Error' });
    fixture.detectChanges();
    expect(fixture.componentInstance.noticias).toEqual([]);
    expect(fixture.nativeElement.textContent).toContain('No se pudieron cargar');
  });

  it('crear envía el formulario al backend y navega al listado', () => {
    vi.spyOn(window, 'alert').mockImplementation(() => {});
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(AdminNoticiaForm);
    fixture.componentInstance.noticia = { ...news };
    fixture.componentInstance.guardarNoticia();
    const req = http.expectOne('/api/admin/noticias');
    expect(req.request.method).toBe('POST');
    expect(req.request.body.titulo).toBe(news.titulo);
    req.flush(news);
    expect(navigate).toHaveBeenCalledWith(['/admin/noticias']);
  });

  it('editar carga la noticia y guarda cambios con PUT', () => {
    params.set('id', '1');
    vi.spyOn(window, 'alert').mockImplementation(() => {});
    vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(AdminNoticiaForm);
    http.expectOne('/api/admin/noticias/1').flush(news);
    fixture.componentInstance.noticia.titulo = 'Editada';
    fixture.componentInstance.guardarNoticia();
    const req = http.expectOne('/api/admin/noticias/1');
    expect(req.request.method).toBe('PUT');
    expect(req.request.body.titulo).toBe('Editada');
    req.flush({ ...news, titulo: 'Editada' });
  });

  it('guardar sin imagen no genera una petición', () => {
    const fixture = TestBed.createComponent(AdminNoticiaForm);
    fixture.componentInstance.guardarNoticia();
    expect(fixture.componentInstance.errorImagen).toBeTruthy();
    http.expectNone('/api/admin/noticias');
  });

  it('errores al guardar mantienen el formulario', () => {
    vi.spyOn(window, 'alert').mockImplementation(() => {});
    const fixture = TestBed.createComponent(AdminNoticiaForm);
    fixture.componentInstance.noticia = { ...news };
    fixture.componentInstance.guardarNoticia();
    http.expectOne('/api/admin/noticias').flush({}, { status: 500, statusText: 'Error' });
    expect(fixture.componentInstance.errorGuardado).toContain('No se pudo guardar');
    expect(fixture.componentInstance.noticia.titulo).toBe(news.titulo);
  });

  it('noticias públicas consultan exclusivamente la API pública', () => {
    localStorage.setItem('municipio-noticias', JSON.stringify([{ ...news, titulo: 'Legacy' }]));
    const fixture = TestBed.createComponent(Noticias);
    http.expectOne('/api/noticias').flush([news]);
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain(news.titulo);
    expect(fixture.nativeElement.textContent).not.toContain('Legacy');
  });

  it('Home usa el endpoint de destacadas', () => {
    const fixture = TestBed.createComponent(NoticiasDestacadas);
    http.expectOne('/api/noticias/destacadas').flush([news]);
    fixture.detectChanges();
    expect(fixture.componentInstance.noticiaPrincipal?.id).toBe(1);
    expect(fixture.nativeElement.textContent).toContain(news.titulo);
  });

  it('archivar mantiene el contrato DELETE del backend', () => {
    TestBed.inject(NoticiasService).archivarDesdeApi(1).subscribe();
    const req = http.expectOne('/api/admin/noticias/1');
    expect(req.request.method).toBe('DELETE');
    req.flush(null);
  });
});
