import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { provideZonelessChangeDetection } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { Home } from './home';

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
