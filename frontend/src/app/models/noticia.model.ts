export interface Noticia {
  id: number;
  titulo: string;
  slug: string;
  bajada: string;
  contenido: string;
  imagen: string;
  categoria: string;
  fechaPublicacion: string;
  estado: 'PUBLICADA' | 'BORRADOR' | 'ARCHIVADA';
  destacada: boolean;
}
