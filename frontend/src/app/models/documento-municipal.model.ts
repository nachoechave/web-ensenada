export interface DocumentoMunicipal {
  id: number;
  titulo: string;
  fecha: string;
  hora: string;
  anio: number;
  tipo: 'Boletín oficial' | 'Hacienda';
  descripcion?: string;
  archivoUrl: string;
}
