import { Component, inject } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { externalLinks } from '../../config/external-links';
import { Navbar } from '../../components/navbar/navbar';
import { Footer } from '../../components/footer/footer';

@Component({
  selector: 'app-servicio-externo',
  imports: [Navbar, Footer],
  template: `<app-navbar />
    <main class="service-page">
      <h1>{{ titulo }}</h1>
      <p>Este servicio se gestiona en un sistema municipal externo.</p>
      @if (url) {
        <a [href]="url" target="_blank" rel="noopener noreferrer">Acceder a {{ titulo }}</a>
      } @else {
        <p>Enlace pendiente de configuración.</p>
      }
    </main>
    <app-footer />`,
  styles: `
    .service-page {
      max-width: 70rem;
      margin: 3rem auto;
      padding: 1.5rem;
      min-height: 40vh;
    }
  `,
})
export class ServicioExterno {
  private readonly data = inject(ActivatedRoute).snapshot.data;
  readonly titulo = this.data['nombre'] as string;
  readonly url = externalLinks[this.data['servicio'] as keyof typeof externalLinks];
}
