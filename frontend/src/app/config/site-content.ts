import { PortalContent } from '../models/portal-content.model';

export const defaultPortalContent: PortalContent = {
  topbar: {
    telefono: '(0221) 460-1000',
    direccion: 'La Merced 121, Ensenada, Buenos Aires',
    horarios: 'Lun a Vie de 8:00 a 14:00',
    facebook: 'https://www.facebook.com/municipalidaddeensenada',
    instagram: 'https://www.instagram.com/municipalidaddeensenada',
    youtube: 'https://www.youtube.com/',
  },
  navbar: {
    inicio: 'Inicio',
    municipio: 'Municipio',
    areas: 'Áreas',
    tramites: 'Trámites',
    noticias: 'Noticias',
    hacienda: 'Hacienda',
    contacto: 'Contacto',
    boletinTexto: 'Boletín Oficial',
    boletinUrl: 'https://www.ensenada.gov.ar/boletin-oficial/',
  },
  hero: {
    eyebrow: 'Ensenada · Buenos Aires',
    titulo: 'Una ciudad cerca de su gente',
    bajada:
      'Servicios, trámites, información y novedades en un portal que conecta a Ensenada con sus vecinos y vecinas.',
    imagen: '/assets/ensenada-hero.jpg',
    imagenes: ['/assets/ensenada-hero.jpg'],
    intervaloSegundos: 4,
    ctaPrimarioTexto: 'Ver trámites',
    ctaPrimarioUrl: '#accesos',
    ctaSecundarioTexto: 'Últimas noticias',
    ctaSecundarioUrl: '#actualidad',
  },
  accesosTitulo: 'Accesos rápidos',
  accesos: [
    {
      titulo: 'Licencias',
      descripcion: 'Conducir, habilitaciones y otros permisos.',
      url: 'https://www.ensenada.gov.ar/',
      icono: 'document',
    },
    {
      titulo: 'Ambiente',
      descripcion: 'Recolección, reciclado y espacios verdes.',
      url: 'https://www.ensenada.gov.ar/',
      icono: 'recycle',
    },
    {
      titulo: 'Desarrollo Social',
      descripcion: 'Programas y asistencia a la comunidad.',
      url: 'https://www.ensenada.gov.ar/',
      icono: 'users',
    },
    {
      titulo: 'Obras Públicas',
      descripcion: 'Obras, mantenimiento y espacio urbano.',
      url: 'https://www.ensenada.gov.ar/',
      icono: 'hardhat',
    },
    {
      titulo: 'Salud',
      descripcion: 'Centros de salud y campañas.',
      url: 'https://www.ensenada.gov.ar/',
      icono: 'heart',
    },
    {
      titulo: 'Deportes',
      descripcion: 'Escuelas, actividades y espacios deportivos.',
      url: 'https://www.ensenada.gov.ar/',
      icono: 'ball',
    },
  ],
  tramites: {
    kicker: 'Servicios destacados',
    titulo: 'Accesos rápidos',
    bajada: 'Encontrá de forma directa los trámites y servicios municipales más consultados.',
    items: [
      { titulo: 'Licencias', descripcion: 'Conducir, habilitaciones y otros permisos.', url: 'https://www.ensenada.gov.ar/', icono: 'document' },
      { titulo: 'Ambiente', descripcion: 'Recolección, reciclado y espacios verdes.', url: 'https://www.ensenada.gov.ar/', icono: 'recycle' },
      { titulo: 'Desarrollo Social', descripcion: 'Programas y asistencia a la comunidad.', url: 'https://www.ensenada.gov.ar/', icono: 'users' },
      { titulo: 'Obras Públicas', descripcion: 'Obras, mantenimiento y espacio urbano.', url: 'https://www.ensenada.gov.ar/', icono: 'hardhat' },
      { titulo: 'Salud', descripcion: 'Centros de salud y campañas.', url: 'https://www.ensenada.gov.ar/', icono: 'heart' },
      { titulo: 'Deportes', descripcion: 'Escuelas, actividades y espacios deportivos.', url: 'https://www.ensenada.gov.ar/', icono: 'ball' },
    ],
  },
  noticias: {
    kicker: 'Noticias destacadas',
    titulo: 'Lo que pasa en Ensenada',
    bajada: 'Novedades, obras, actividades y servicios de la ciudad.',
    boton: 'Ver todas las noticias',
  },
  areas: {
    kicker: 'Áreas municipales',
    titulo: 'Conocé nuestras áreas',
    bajada: 'Información, programas y servicios de cada área del Municipio.',
    items: [
      { titulo: 'Hacienda', descripcion: 'Información fiscal y presupuesto.', url: '/hacienda', icono: 'building' },
      { titulo: 'Cultura', descripcion: 'Actividades y patrimonio.', url: 'https://www.ensenada.gov.ar/', icono: 'culture' },
      { titulo: 'Deportes', descripcion: 'Escuelas y eventos deportivos.', url: 'https://www.ensenada.gov.ar/', icono: 'ball' },
      { titulo: 'Salud', descripcion: 'Centros de salud y prevención.', url: 'https://www.ensenada.gov.ar/', icono: 'heart' },
      { titulo: 'Obras Públicas', descripcion: 'Infraestructura y espacio urbano.', url: 'https://www.ensenada.gov.ar/', icono: 'hardhat' },
      { titulo: 'Desarrollo Social', descripcion: 'Inclusión y apoyo a la comunidad.', url: 'https://www.ensenada.gov.ar/', icono: 'users' },
    ],
  },
  intendencia: {
    kicker: 'Intendente de Ensenada',
    nombre: 'Mario Secco',
    descripcion:
      'Intendente de Ensenada desde 2003. Información institucional sobre la Intendencia y el Departamento Ejecutivo municipal.',
    imagen: '/assets/mario-secco.jpg',
    botonTexto: 'Conocer la Intendencia',
    botonUrl: 'https://www.ensenada.gov.ar/intendencia-municipal/',
    secundarioTexto: 'Ver autoridades',
    secundarioUrl: 'https://www.ensenada.gov.ar/',
    cita:
      'Seguimos construyendo una Ensenada con más derechos, más obras y una comunidad cada vez más unida.',
  },
  agenda: {
    kicker: 'Agenda y actualidad',
    titulo: 'Próximos eventos',
    bajada: 'Actividades, inauguraciones y propuestas para disfrutar en la ciudad.',
    items: [
      { dia: '15', mes: 'MAR', titulo: 'Feria de emprendedores', lugar: 'Paseo Costero', hora: '10:00 a 18:00' },
      { dia: '17', mes: 'MAR', titulo: 'Torneo de fútbol infantil', lugar: 'Polideportivo Municipal', hora: '09:00 a 14:00' },
      { dia: '21', mes: 'MAR', titulo: 'Jornada de salud integral', lugar: 'Centro de Salud N°1', hora: '08:00 a 13:00' },
    ],
  },
  footer: {
    descripcion: 'Portal oficial de información y servicios de la Municipalidad de Ensenada.',
    telefono: '(0221) 460-1000',
    direccion: 'La Merced 121, Ensenada, Buenos Aires',
    email: 'info@ensenada.gob.ar',
    mapaUrl: 'https://www.google.com/maps?q=La%20Merced%20121,%20Ensenada,%20Buenos%20Aires',
    copyright: '© Municipalidad de Ensenada. Todos los derechos reservados.',
  },
};

export function cloneDefaultPortalContent(): PortalContent {
  return structuredClone(defaultPortalContent);
}

// Alias temporal para componentes antiguos que todavía importan siteContent.
export const siteContent = {
  heroTitulo: defaultPortalContent.hero.titulo,
  heroBajada: defaultPortalContent.hero.bajada,
  footerDescripcion: defaultPortalContent.footer.descripcion,
};
