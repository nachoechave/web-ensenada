export type RolUsuario =
  | 'SUPER_ADMIN'
  | 'PRENSA'
  | 'HACIENDA'
  | 'BOLETIN_OFICIAL'
  | 'CONTENIDO';

export interface LoginRequest {
  email: string;
  password: string;
}

export interface LoginResponse {
  token: string;
  nombre: string;
  email: string;
  rol?: string;
  roles?: RolUsuario[];
}

export interface UsuarioActual {
  nombre: string;
  email: string;
  rol?: string;
  roles: RolUsuario[];
}

export interface ActualizarPerfilRequest {
  nombre: string;
  email: string;
}

export interface CambiarPasswordRequest {
  passwordActual: string;
  passwordNueva: string;
}
