export interface PortalNavbarItem {
  texto: string;
  url: string;
  visible: boolean;
}

export interface PortalLinkItem {
  titulo: string;
  descripcion: string;
  url: string;
  icono: string;
}

export interface PortalSecretariaItem {
  id: string;
  nombre: string;
  responsable: string;
  direccion: string;
  telefono: string;
  horario: string;
  email: string;
}

export interface PortalAgendaItem {
  dia: string;
  mes: string;
  titulo: string;
  lugar: string;
  hora: string;
}

export interface PortalContent {
  topbar: {
    telefono: string;
    direccion: string;
    horarios: string;
    facebook: string;
    instagram: string;
    youtube: string;
  };
  navbar: {
    inicio: string;
    municipio: string;
    areas: string;
    tramites: string;
    noticias: string;
    hacienda: string;
    contacto: string;
    boletinTexto: string;
    boletinUrl: string;
    items: PortalNavbarItem[];
  };
  hero: {
    eyebrow: string;
    titulo: string;
    bajada: string;
    imagen: string;
    imagenes: string[];
    intervaloSegundos: number;
    ctaPrimarioTexto: string;
    ctaPrimarioUrl: string;
    ctaSecundarioTexto: string;
    ctaSecundarioUrl: string;
  };
  accesosTitulo: string;
  accesos: PortalLinkItem[];
  tramites: {
    kicker: string;
    titulo: string;
    bajada: string;
    items: PortalLinkItem[];
  };
  noticias: {
    kicker: string;
    titulo: string;
    bajada: string;
    boton: string;
  };
  areas: {
    kicker: string;
    titulo: string;
    bajada: string;
    paginaTitulo: string;
    paginaBajada: string;
    secretarias: PortalSecretariaItem[];
    items: PortalLinkItem[];
  };
  intendencia: {
    kicker: string;
    nombre: string;
    descripcion: string;
    imagen: string;
    botonTexto: string;
    botonUrl: string;
    secundarioTexto: string;
    secundarioUrl: string;
    cita: string;
    historiaTitulo: string;
    historia: string;
    galeria: string[];
  };
  agenda: {
    kicker: string;
    titulo: string;
    bajada: string;
    items: PortalAgendaItem[];
  };
  footer: {
    descripcion: string;
    telefono: string;
    direccion: string;
    email: string;
    mapaUrl: string;
    copyright: string;
  };
}
