import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Footer } from '../../components/footer/footer';
import { Navbar } from '../../components/navbar/navbar';

type ContactoUtil = { nombre: string; telefono: string; urgente?: boolean };

@Component({ selector: 'app-telefonos-utiles', imports: [Navbar, Footer, RouterLink], templateUrl: './telefonos-utiles.html', styleUrl: './telefonos-utiles.css' })
export class TelefonosUtiles {
  readonly salud: ContactoUtil[] = [
    { nombre: 'Emergencias médicas', telefono: '107', urgente: true },
    { nombre: 'Dirección de Medio Ambiente', telefono: '460-1764' },
    { nombre: 'Bromatología y Veterinaria', telefono: '221 599-1678' },
    { nombre: 'Hospital Punta Lara', telefono: '644-1377/78 / 221 358-2138' },
    { nombre: 'Hospital Horacio Cestino', telefono: '469-1041' },
    { nombre: 'Hospital El Dique', telefono: '483-4758' },
  ];
  readonly unidades: ContactoUtil[] = [
    { nombre: 'CIC', telefono: '221 559-4516' }, { nombre: 'Mosconi', telefono: '460-2358' },
    { nombre: 'El Molino', telefono: '466-0789 / 221 525-4753' }, { nombre: 'U.S. N.º 184', telefono: '466-1334 / 221 420-8875' },
    { nombre: 'Villa Tranquila', telefono: '460-1219 / 221 556-8646' }, { nombre: 'Centro', telefono: '221 695-7644' },
    { nombre: 'Malvinas', telefono: '460-2357 / 221 615-8141' }, { nombre: 'Villa Catella', telefono: '221 601-7340' },
    { nombre: 'Isla Santiago', telefono: '427-7597 / 221 222-7098' }, { nombre: 'Campamento', telefono: '469-1515 / 221 317-9570' },
    { nombre: 'Juana Azurduy', telefono: '221 564-0862' }, { nombre: 'Cajade', telefono: '469-0399 / 221 355-4912' },
    { nombre: 'Emma Córdoba', telefono: '221 601-7051' },
  ];
  readonly seguridad: ContactoUtil[] = [
    { nombre: 'Policía', telefono: '911', urgente: true }, { nombre: 'Bomberos', telefono: '100', urgente: true },
    { nombre: 'Prefectura Naval Argentina', telefono: '106 / 469-0376', urgente: true },
    { nombre: 'Centro Operativo de Monitoreo', telefono: '469-2892' }, { nombre: 'Base Policía Local', telefono: '460-1260' },
    { nombre: 'Comisaría Primera', telefono: '469-2223' }, { nombre: 'Comisaría Punta Lara', telefono: '466-1234' },
    { nombre: 'Comisaría El Dique', telefono: '423-1743' }, { nombre: 'Comisaría de la Mujer', telefono: '460-2453' },
    { nombre: 'Cuartel Central de Bomberos', telefono: '469-2162 / 221 557-0192' },
  ];
}
