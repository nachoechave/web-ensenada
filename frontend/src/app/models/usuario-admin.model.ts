import { RolUsuario } from './auth.model';

export interface UsuarioAdmin {
  id: number;
  nombre: string;
  email: string;
  roles: RolUsuario[];
  activo: boolean;
}
