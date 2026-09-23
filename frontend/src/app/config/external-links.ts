// URLs existentes en el repositorio. Completar las pendientes con destinos oficiales verificados.
export const externalLinks = {
  boletinOficial: 'https://boletinoficial.ensenada.gov.ar/index.php',
  proveedores: null as string | null, // PENDIENTE: URL oficial del registro
  turnos: 'https://www.ensenada.gov.ar/turnos/',
  tasas: 'https://pagos.ensenada.gov.ar/nuevo_index.php',
  boletaDigital: 'https://avisos.ensenada.gov.ar/',
  twitter: 'https://twitter.com/prensaensenada',
  instagram: 'https://www.instagram.com/prensaensenada/',
  facebook: 'https://www.facebook.com/prensaensenadaok',
};
export const municipalLinks: { titulo: string; url: string | null; interno?: boolean }[] = [
  { titulo: 'Boletín Oficial', url: externalLinks.boletinOficial },
  { titulo: 'Hacienda', url: '/hacienda', interno: true },
  { titulo: 'Registro Municipal de Proveedores', url: externalLinks.proveedores },
  { titulo: 'Turnos veterinarios', url: externalLinks.turnos },
  { titulo: 'Tasas municipales', url: externalLinks.tasas },
  { titulo: 'Boleta digital', url: externalLinks.boletaDigital },
];
