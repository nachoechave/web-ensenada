import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';

@Component({
  selector: 'app-historia',
  imports: [Navbar, Footer, RouterLink],
  templateUrl: './historia.html',
  styleUrl: './historia.css',
})
export class Historia {
  readonly hitos = [
    { fecha: '1520', texto: 'La expedición de Fernando de Magallanes reconoce la Caleta de Ensenada.' },
    { fecha: '1731', texto: 'Se levanta una fortificación para proteger el puerto y el poblado en crecimiento.' },
    { fecha: '5 de mayo de 1801', texto: 'Se aprueba la traza proyectada por Pedro Antonio Cerviño, fecha fundacional de la ciudad.' },
    { fecha: '5 de junio de 1810', texto: 'La Primera Junta habilita oficialmente el Puerto de la Ensenada.' },
    { fecha: '17 de febrero de 1856', texto: 'Ensenada es declarada cabecera de Partido y se instala regularmente el Municipio.' },
    { fecha: '1882', texto: 'Dardo Rocha declara al Municipio capital provisional de la provincia de Buenos Aires.' },
    { fecha: '1958', texto: 'La Ordenanza N.º 4 adopta oficialmente el escudo municipal.' },
    { fecha: '1999', texto: 'El Partido adopta oficialmente su bandera identificatoria.' },
  ];
}
