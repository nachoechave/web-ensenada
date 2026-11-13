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

  it('muestra la foto completa como imagen y permite seleccionar las fotos en el orden publicado', async () => {
    TestBed.configureTestingModule({
      providers: [provideZonelessChangeDetection(), provideHttpClient(),
        provideHttpClientTesting(), provideRouter([])],
    });
    const http = TestBed.inject(HttpTestingController);
    const fixture = TestBed.createComponent(Home);
    await fixture.whenStable();
    http.expectOne('/api/noticias/destacadas').flush([]);
    http.expectOne('/api/site-content').flush({ hero: {
      imagenes: ['/uploads/sitio/segunda.webp', '/uploads/sitio/primera.webp'],
      intervaloSegundos: 30,
    } });
    await fixture.whenStable();

    const hero: HTMLElement = fixture.nativeElement.querySelector('.portal-hero');
    const slides = [...hero.querySelectorAll<HTMLImageElement>('img.hero-slide')];
    const dots = [...hero.querySelectorAll<HTMLButtonElement>('.hero-dots button')];
    expect(hero.querySelector('.hero-slides')?.parentElement).toBe(hero);
    expect(hero.querySelector('.hero-shade')?.parentElement).toBe(hero);
    expect(hero.querySelector('.hero-grid .hero-copy #hero-title')?.textContent)
      .toContain('Una ciudad cerca de su gente');
    expect(slides.map((slide) => slide.getAttribute('src')))
      .toEqual(['/uploads/sitio/segunda.webp', '/uploads/sitio/primera.webp']);
    expect(slides[0].getAttribute('fetchpriority')).toBe('high');
    expect(slides[0].classList.contains('active')).toBe(true);
    expect(dots[0].getAttribute('aria-current')).toBe('true');

    dots[1].click();
    await fixture.whenStable();
    expect(slides[0].classList.contains('active')).toBe(false);
    expect(slides[1].classList.contains('active')).toBe(true);
    expect(dots[0].hasAttribute('aria-current')).toBe(false);
    expect(dots[1].getAttribute('aria-current')).toBe('true');
    fixture.destroy();
    http.verify();
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
