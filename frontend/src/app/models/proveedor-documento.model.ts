export interface DocumentoProveedor {
  id: number;
  titulo: string;
  descripcion: string;
  nombreOriginal: string;
  url: string;
  tipoMime: string;
  tamanio: number;
  orden: number;
  activo: boolean;
}
