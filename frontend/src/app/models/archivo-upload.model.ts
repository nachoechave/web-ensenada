export interface ArchivoUploadResponse {
  url: string;
  nombreOriginal: string;
  contentType: string;
  size: number;
}

export type SeccionArchivo = 'noticias' | 'hacienda';
