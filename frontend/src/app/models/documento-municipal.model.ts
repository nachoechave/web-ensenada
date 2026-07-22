export interface DocumentoMunicipal {
  id: number;
  titulo: string;
  fecha: string;
  hora: string;
  anio: number;
  tipo: 'Hacienda';
  descripcion?: string;
  archivoUrl: string;
}
