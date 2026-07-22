import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

import { RolUsuario } from '../models/auth.model';
import { UsuarioAdmin } from '../models/usuario-admin.model';

export type UsuarioCrearRequest = Omit<UsuarioAdmin, 'id'> & {
  password: string;
};

@Injectable({
  providedIn: 'root',
})
export class UsuariosAdminService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8080/api/admin/usuarios';

  readonly rolesDisponibles: RolUsuario[] = [
    'SUPER_ADMIN',
    'PRENSA',
    'HACIENDA',
    'CONTENIDO',
  ];

  obtenerUsuarios(): Observable<UsuarioAdmin[]> {
    return this.http.get<UsuarioAdmin[]>(this.apiUrl);
  }

  guardar(usuario: UsuarioCrearRequest): Observable<UsuarioAdmin> {
    return this.http.post<UsuarioAdmin>(this.apiUrl, usuario);
  }

  actualizarRoles(id: number, roles: RolUsuario[]): Observable<UsuarioAdmin> {
    return this.http.put<UsuarioAdmin>(`${this.apiUrl}/${id}/roles`, { roles });
  }
}
