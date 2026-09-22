import { environment } from '../../environments/environment';
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
  private readonly apiUrl = environment.apiUrl + '/admin/usuarios';

  readonly rolesDisponibles: RolUsuario[] = ['PRENSA'];

  obtenerUsuarios(): Observable<UsuarioAdmin[]> {
    return this.http.get<UsuarioAdmin[]>(this.apiUrl);
  }

  guardar(usuario: UsuarioCrearRequest): Observable<UsuarioAdmin> {
    return this.http.post<UsuarioAdmin>(this.apiUrl, usuario);
  }

  actualizarActivo(id: number, activo: boolean): Observable<UsuarioAdmin> {
    return this.http.put<UsuarioAdmin>(`${this.apiUrl}/${id}/activo`, { activo });
  }

  actualizarRoles(id: number, roles: RolUsuario[]): Observable<UsuarioAdmin> {
    return this.http.put<UsuarioAdmin>(`${this.apiUrl}/${id}/roles`, { roles });
  }
}
