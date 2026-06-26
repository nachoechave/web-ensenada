export interface Noticia {
  id: number;
  titulo: string;
  bajada: string;
  contenido: string;
  imagen: string;
  categoria: string;
  fecha: string;
  estado: 'Publicada' | 'Borrador';
}