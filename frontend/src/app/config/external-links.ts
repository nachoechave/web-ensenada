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

export type MunicipalLink = {
  titulo: string;
  descripcion: string;
  codigo: string;
  cta: string;
  url: string | null;
  interno?: boolean;
};

export const municipalLinks: MunicipalLink[] = [
  { titulo: 'Hacienda', descripcion: 'Publicaciones e información fiscal del área.', codigo: 'HA', cta: 'Ver publicaciones', url: '/hacienda', interno: true },
  { titulo: 'Boletín Oficial', descripcion: 'Normativa y publicaciones oficiales del Municipio.', codigo: 'BO', cta: 'Consultar', url: externalLinks.boletinOficial },
  { titulo: 'Turnos veterinarios', descripcion: 'Solicitud de turnos para servicios veterinarios.', codigo: 'TV', cta: 'Solicitar turno', url: externalLinks.turnos },
  { titulo: 'Registro de Proveedores', descripcion: 'Información y acceso para proveedores municipales.', codigo: 'RP', cta: 'Acceder', url: externalLinks.proveedores },
  { titulo: 'Tasas municipales', descripcion: 'Consulta y pago online de tasas municipales.', codigo: 'TM', cta: 'Consultar tasas', url: externalLinks.tasas },
  { titulo: 'Boleta digital', descripcion: 'Gestión de avisos y documentación tributaria digital.', codigo: 'BD', cta: 'Ingresar', url: externalLinks.boletaDigital },
];
