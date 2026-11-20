import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Home } from './home';
import { vi } from 'vitest';

describe('Home: títulos publicados del CMS', () => {
  it('actualiza el título de Áreas después del primer render, sin interacción ni carrusel', async () => {
    TestBed.configureTestingModule({
      providers: [provideZonelessChangeDetection(), provideHttpClient(),
        provideHttpClientTesting(), provideRouter([])],
    });
    const http = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(Home);
    await fixture.whenStable();
    http.expectOne('/api/noticias/destacadas').flush([]);
    await fixture.whenStable();

    http.expectOne('/api/site-content').flush({ areas: {
      titulo: 'Conocé nuestras secretarías.',
      bajada: 'Información y servicios de cada área del Municipio',
      paginaTitulo: 'Título exclusivo del directorio',
      secretarias: [],
    } });
    await fixture.whenStable();

    expect(fixture.nativeElement.querySelector('#areas-title').textContent)
      .toBe('Conocé nuestras secretarías.');
    expect(fixture.nativeElement.querySelector('#areas').textContent)
      .toContain('Información y servicios de cada área del Municipio');
    expect(fixture.nativeElement.querySelector('#areas').textContent)
      .not.toContain('Título exclusivo del directorio');
    http.verify();
  });
});

describe('Hero: avance automático', () => {
  afterEach(() => {
    vi.useRealTimers();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it.each([false, true])('avanza, vuelve al inicio y limpia el timer (movimiento reducido: %s)', async (reduced) => {
    vi.stubGlobal('matchMedia', vi.fn(() => ({ matches: reduced } as MediaQueryList)));
    TestBed.configureTestingModule({
      providers: [provideZonelessChangeDetection(), provideHttpClient(),
        provideHttpClientTesting(), provideRouter([])],
    });
    const http = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(Home);
    await fixture.whenStable();
    http.expectOne('/api/noticias/destacadas').flush([]);
    await fixture.whenStable();
    vi.useFakeTimers();
    http.expectOne('/api/site-content').flush({ hero: {
      imagenes: ['/uno.jpg', '/dos.jpg', '/tres.jpg'], intervaloSegundos: 3,
    } });
    await vi.advanceTimersByTimeAsync(3001);
    expect(fixture.componentInstance.heroIndex()).toBe(1);
    expect(fixture.nativeElement.querySelectorAll('.hero-slide')[1].classList.contains('active')).toBe(true);
    await vi.advanceTimersByTimeAsync(6000);
    expect(fixture.componentInstance.heroIndex()).toBe(0);
    fixture.componentInstance.seleccionarHero(1);
    await vi.advanceTimersByTimeAsync(3000);
    expect(fixture.componentInstance.heroIndex()).toBe(2);
    fixture.destroy();
    await vi.advanceTimersByTimeAsync(6000);
    expect(fixture.componentInstance.heroIndex()).toBe(2);
    http.verify();
  });
});


describe('Teléfonos útiles', () => {
  it('muestra enlaces tel seguros y contactos sin número no clickeables', async () => {
    TestBed.configureTestingModule({
      providers: [provideZonelessChangeDetection(), provideHttpClient(),
        provideHttpClientTesting(), provideRouter([])],
    });
    const http = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(Home);
    await fixture.whenStable();
    http.expectOne('/api/noticias/destacadas').flush([]);
    await fixture.whenStable();
    http.expectOne('/api/site-content').flush({ telefonosUtiles: {
      titulo: 'Contactos de emergencia',
      visible: true,
      items: [
        { nombre: 'Policía', numero: '911', descripcion: 'Urgencias', icono: 'policia', visible: true },
        { nombre: 'Bomberos', numero: '', descripcion: 'Ver cuarteles', icono: 'bomberos', visible: true },
        { nombre: 'Oculto', numero: '107', descripcion: '', icono: 'telefono', visible: false },
      ],
    } });
    await fixture.whenStable();
    const tarjetas = fixture.nativeElement.querySelectorAll('.telefono-card');
    expect(tarjetas.length).toBe(2);
    expect(tarjetas[0].getAttribute('href')).toBe('tel:911');
    expect(tarjetas[1].getAttribute('href')).toBeNull();
    expect(fixture.componentInstance.enlaceTelefono('0800-333-3218')).toBe('tel:08003333218');
    expect(fixture.componentInstance.enlaceTelefono('javascript:alert(1)')).toBeNull();
    http.verify();
  });
});
