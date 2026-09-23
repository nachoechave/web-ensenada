export const tiposHacienda = {
  SITUACION_ECONOMICO_FINANCIERA: 'Situación económico-financiera',
  PRESUPUESTO: 'Presupuesto',
  ORDENANZA_FISCAL: 'Ordenanza Fiscal e Impositiva',
  ORDENANZA_PRESUPUESTARIA: 'Ordenanza presupuestaria',
  CIERRE_EJERCICIO: 'Cierre de ejercicio',
  OTRO: 'Otro documento financiero',
} as const;
export type TipoPublicacionHacienda = keyof typeof tiposHacienda;
export type EstadoHacienda = 'BORRADOR' | 'PUBLICADA' | 'ARCHIVADA';
export interface ArchivoHacienda {
  id: number;
  nombreOriginal: string;
  url: string;
  tipoMime: string;
  orden: number;
  tamanio: number;
}
export interface PublicacionHacienda {
  id: number;
  titulo: string;
  descripcion: string;
  tipo: TipoPublicacionHacienda;
  fechaPublicacion: string;
  estado: EstadoHacienda;
  archivos: ArchivoHacienda[];
}
export type PublicacionHaciendaRequest = Omit<PublicacionHacienda, 'id' | 'archivos'>;
