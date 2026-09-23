import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import {
  ActivatedRoute,
  ActivatedRouteSnapshot,
  RouterStateSnapshot,
  provideRouter,
  Router,
  convertToParamMap,
} from '@angular/router';
import { BehaviorSubject } from 'rxjs';
import { Title, Meta } from '@angular/platform-browser';
import { vi } from 'vitest';
import { roleGuard } from './core/role.guard';
import { adminGuard } from './core/admin.guard';
import { Hacienda } from './pages/hacienda/hacienda';
import { HaciendaDetalle } from './pages/hacienda-detalle/hacienda-detalle';
import { AdminHacienda } from './pages/admin/admin-hacienda/admin-hacienda';
import { AdminHaciendaForm } from './pages/admin/admin-hacienda-form/admin-hacienda-form';
import { AdminLayout } from './pages/admin/admin-layout/admin-layout';
import { AdminDashboard } from './pages/admin/admin-dashboard/admin-dashboard';
import { AdminUsuarios } from './pages/admin/admin-usuarios/admin-usuarios';
import { AdminLogin } from './pages/admin/admin-login/admin-login';
import { PublicacionHacienda } from './models/hacienda.model';

const pub: PublicacionHacienda = {
  id: 1,
  titulo: 'Presupuesto 2026',
  descripcion: 'Documentación financiera',
  tipo: 'PRESUPUESTO',
  fechaPublicacion: '2026-09-22',
  estado: 'PUBLICADA',
  archivos: [
    {
      id: 10,
      nombreOriginal: 'presupuesto.pdf',
      tipoMime: 'application/pdf',
      url: '/uploads/hacienda/documento.pdf',
      orden: 0,
      tamanio: 100,
    },
    {
      id: 11,
      nombreOriginal: 'pagina.png',
      tipoMime: 'image/png',
      url: '/uploads/hacienda/pagina.png',
      orden: 1,
      tamanio: 100,
    },
  ],
};
describe('Hacienda', () => {
  it('login explica el límite de intentos', () => {
    const fixture = TestBed.createComponent(AdminLogin);
    fixture.componentInstance.email = 'op@example.test';
    fixture.componentInstance.password = 'Test-password-1234';
    fixture.componentInstance.login();
    http.expectOne('/api/auth/login').flush({}, {status:429, statusText:'Too Many Requests'});
    expect(fixture.componentInstance.error).toContain('Demasiados intentos');
  });
  let http: HttpTestingController;
  let params: Map<string, string>;
  let routeParams: BehaviorSubject<ReturnType<typeof convertToParamMap>>;
  beforeEach(() => {
    localStorage.clear();
    params = new Map();
    routeParams = new BehaviorSubject(convertToParamMap({ id: '1' }));
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: { snapshot: { paramMap: params }, paramMap: routeParams },
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
  function session(rol: string) {
    localStorage.setItem('admin-token', 'test');
    localStorage.setItem(
      'admin-user',
      JSON.stringify({ nombre: 'Operador', email: 'op@example.test', roles: [rol] }),
    );
  }
  it.each(['HACIENDA', 'SUPER_ADMIN'])('permite el guard a %s', (rol) => {
    session(rol);
    expect(
      TestBed.runInInjectionContext(() =>
        roleGuard(
          { data: { roles: ['HACIENDA'] } } as unknown as ActivatedRouteSnapshot,
          {} as RouterStateSnapshot,
        ),
      ),
    ).toBe(true);
    expect(
      TestBed.runInInjectionContext(() =>
        adminGuard({} as ActivatedRouteSnapshot, {} as RouterStateSnapshot),
      ),
    ).toBe(true);
  });
  it('PRENSA no accede a Hacienda', () => {
    session('PRENSA');
    expect(
      String(
        TestBed.runInInjectionContext(() =>
          roleGuard(
            { data: { roles: ['HACIENDA'] } } as unknown as ActivatedRouteSnapshot,
            {} as RouterStateSnapshot,
          ),
        ),
      ),
    ).toBe('/admin');
  });
  it.each(['PRENSA', 'HACIENDA', 'SUPER_ADMIN'])('menú de %s no ofrece módulos ajenos', (rol) => {
    session(rol);
    const fixture = TestBed.createComponent(AdminLayout);
    fixture.detectChanges();
    const nav = fixture.nativeElement.querySelector('nav').textContent;
    expect(nav.includes('Hacienda')).toBe(rol !== 'PRENSA');
    expect(nav.includes('Noticias')).toBe(rol !== 'HACIENDA');
    expect(nav.includes('Usuarios')).toBe(rol === 'SUPER_ADMIN');
  });
  it('dashboard Hacienda no consulta noticias ni usuarios', () => {
    session('HACIENDA');
    const fixture = TestBed.createComponent(AdminDashboard);
    http.expectOne('/api/admin/hacienda').flush([pub]);
    fixture.detectChanges();
    http.expectNone('/api/admin/noticias');
    expect(fixture.nativeElement.querySelector('a[href="/admin/noticias"]')).toBeNull();
  });
  it('listado público muestra loading, datos y vínculos', () => {
    const fixture = TestBed.createComponent(Hacienda);
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Cargando');
    http.expectOne('/api/hacienda').flush([pub]);
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain(pub.titulo);
    expect(fixture.nativeElement.querySelector('a[href="/hacienda/1"]')).toBeTruthy();
  });
  it('listado público informa vacío', () => {
    const fixture = TestBed.createComponent(Hacienda);
    http.expectOne('/api/hacienda').flush([]);
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('No hay publicaciones');
  });
  it('error público permite reintentar', () => {
    const fixture = TestBed.createComponent(Hacienda);
    http.expectOne('/api/hacienda').flush({}, { status: 500, statusText: 'Error' });
    fixture.detectChanges();
    expect(fixture.nativeElement.querySelector('[role="alert"]')).toBeTruthy();
    fixture.componentInstance.cargar();
    http.expectOne('/api/hacienda').flush([pub]);
    expect(fixture.componentInstance.error()).toBe('');
  });
  it('detalle muestra PDF, imágenes ordenadas y metadata', () => {
    const fixture = TestBed.createComponent(HaciendaDetalle);
    http.expectOne('/api/hacienda/1').flush(pub);
    fixture.detectChanges();
    expect(TestBed.inject(Title).getTitle()).toBe(
      'Presupuesto 2026 | Hacienda | Municipalidad de Ensenada',
    );
    expect(TestBed.inject(Meta).getTag('name="description"')?.content).toBe(pub.descripcion);
    expect(
      fixture.nativeElement.querySelector('a[href="/uploads/hacienda/documento.pdf"]').textContent,
    ).toContain('Ver documento');
    expect(fixture.nativeElement.querySelector('img').getAttribute('loading')).toBe('lazy');
    expect(fixture.nativeElement.querySelector('img').alt).toContain('pagina.png');
  });
  it('detalle no disponible muestra error y limpia al cambiar id', () => {
    const fixture = TestBed.createComponent(HaciendaDetalle);
    http.expectOne('/api/hacienda/1').flush(pub);
    routeParams.next(convertToParamMap({ id: '2' }));
    http.expectOne('/api/hacienda/2').flush({}, { status: 404, statusText: 'Not found' });
    fixture.detectChanges();
    expect(fixture.nativeElement.textContent).toContain('Publicación no disponible');
    expect(fixture.nativeElement.textContent).not.toContain(pub.titulo);
  });
  it('admin filtra borradores sin perder la lista completa', () => {
    const fixture = TestBed.createComponent(AdminHacienda);
    http.expectOne('/api/admin/hacienda').flush([pub, { ...pub, id: 2, estado: 'BORRADOR' }]);
    fixture.componentInstance.filtro.set('BORRADOR');
    expect(fixture.componentInstance.filtradas().map((p) => p.id)).toEqual([2]);
  });
  it('admin publica y recarga desde backend', () => {
    const fixture = TestBed.createComponent(AdminHacienda);
    http.expectOne('/api/admin/hacienda').flush([{ ...pub, estado: 'BORRADOR' }]);
    fixture.componentInstance.cambiarEstado({ ...pub, estado: 'BORRADOR' });
    const req = http.expectOne('/api/admin/hacienda/1');
    expect(req.request.body.estado).toBe('PUBLICADA');
    req.flush(pub);
    http.expectOne('/api/admin/hacienda').flush([pub]);
  });
  it('admin archiva por DELETE y actualiza estado', () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const fixture = TestBed.createComponent(AdminHacienda);
    http.expectOne('/api/admin/hacienda').flush([pub]);
    fixture.componentInstance.archivar(pub);
    const req = http.expectOne('/api/admin/hacienda/1');
    expect(req.request.method).toBe('DELETE');
    req.flush(null);
    http.expectOne('/api/admin/hacienda').flush([{ ...pub, estado: 'ARCHIVADA' }]);
  });
  it('formulario crea borrador antes de adjuntar', () => {
    const navigate = vi.spyOn(TestBed.inject(Router), 'navigate').mockResolvedValue(true);
    const fixture = TestBed.createComponent(AdminHaciendaForm);
    fixture.componentInstance.publicacion.titulo = 'Borrador';
    fixture.componentInstance.guardar();
    const req = http.expectOne('/api/admin/hacienda');
    expect(req.request.body.estado).toBe('BORRADOR');
    req.flush({ ...pub, estado: 'BORRADOR', archivos: [] });
    expect(navigate).toHaveBeenCalledWith(['/admin/hacienda/editar', 1]);
  });
  it('formulario edita y mantiene datos ante error', () => {
    params.set('id', '1');
    const fixture = TestBed.createComponent(AdminHaciendaForm);
    http.expectOne('/api/admin/hacienda/1').flush(pub);
    fixture.componentInstance.publicacion.titulo = 'Título editado';
    fixture.componentInstance.guardar();
    const req = http.expectOne('/api/admin/hacienda/1');
    expect(req.request.method).toBe('PUT');
    req.flush({}, { status: 500, statusText: 'Error' });
    expect(fixture.componentInstance.error()).toBeTruthy();
    expect(fixture.componentInstance.publicacion.titulo).toBe('Título editado');
  });
  it('adjunta multipart y conserva cambios editoriales sin guardar', () => {
    params.set('id', '1');
    const fixture = TestBed.createComponent(AdminHaciendaForm);
    http.expectOne('/api/admin/hacienda/1').flush({ ...pub, archivos: [] });
    fixture.componentInstance.publicacion.titulo = 'Sin guardar';
    const file = new File(['%PDF-1.4\n%%EOF'], 'nuevo.pdf', { type: 'application/pdf' });
    fixture.componentInstance.adjuntar({
      target: { files: [file], value: 'nuevo.pdf' },
    } as unknown as Event);
    const req = http.expectOne('/api/admin/hacienda/1/archivos');
    expect(req.request.body.get('archivo')).toBe(file);
    req.flush(pub);
    expect(fixture.componentInstance.archivos().length).toBe(2);
    expect(fixture.componentInstance.publicacion.titulo).toBe('Sin guardar');
  });
  it('rechaza SVG antes de subir', () => {
    params.set('id', '1');
    const fixture = TestBed.createComponent(AdminHaciendaForm);
    http.expectOne('/api/admin/hacienda/1').flush(pub);
    fixture.componentInstance.adjuntar({
      target: { files: [new File(['<svg/>'], 'x.svg', { type: 'image/svg+xml' })], value: '' },
    } as unknown as Event);
    expect(fixture.componentInstance.error()).toContain('PDF');
    http.expectNone('/api/admin/hacienda/1/archivos');
  });
  it('reordena archivos por IDs completos', () => {
    params.set('id', '1');
    const fixture = TestBed.createComponent(AdminHaciendaForm);
    http.expectOne('/api/admin/hacienda/1').flush(pub);
    fixture.componentInstance.mover(1, -1);
    const req = http.expectOne('/api/admin/hacienda/1/archivos/orden');
    expect(req.request.body.ids).toEqual([11, 10]);
    req.flush({ ...pub, archivos: [pub.archivos[1], pub.archivos[0]] });
    expect(fixture.componentInstance.archivos()[0].id).toBe(11);
  });
  it('usuarios envía exactamente un rol operativo', () => {
    const fixture = TestBed.createComponent(AdminUsuarios);
    http.expectOne('/api/admin/usuarios').flush([]);
    fixture.componentInstance.alternarRolNuevo('HACIENDA');
    expect(fixture.componentInstance.usuario.roles).toEqual(['HACIENDA']);
    expect(fixture.componentInstance.rolesDisponibles).not.toContain('SUPER_ADMIN');
  });
});
