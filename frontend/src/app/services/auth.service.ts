import { environment } from '../../environments/environment';
import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, tap } from 'rxjs';

import {
  ActualizarPerfilRequest,
  CambiarPasswordRequest,
  LoginRequest,
  LoginResponse,
  RolUsuario,
  UsuarioActual,
} from '../models/auth.model';

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private readonly http = inject(HttpClient);

  private readonly apiUrl = environment.apiUrl + '/auth';
  private readonly userKey = 'admin-user';
  private readonly legacyTokenKey = 'admin-token';

  login(credenciales: LoginRequest): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${this.apiUrl}/login`, credenciales, { withCredentials: true })
      .pipe(tap((response) => this.guardarSesion(response)));
  }

  obtenerPerfil(): Observable<UsuarioActual> {
    return this.http
      .get<UsuarioActual>(`${this.apiUrl}/me`, { withCredentials: true })
      .pipe(tap((usuario) => this.guardarUsuario(usuario)));
  }

  actualizarPerfil(perfil: ActualizarPerfilRequest): Observable<LoginResponse> {
    return this.http
      .put<LoginResponse>(`${this.apiUrl}/me`, perfil, { withCredentials: true })
      .pipe(tap((response) => this.guardarSesion(response)));
  }

  cambiarPassword(passwords: CambiarPasswordRequest): Observable<void> {
    return this.http.put<void>(`${this.apiUrl}/me/password`, passwords, { withCredentials: true });
  }

  obtenerUsuarioActual(): UsuarioActual | null {
    const usuarioGuardado =
      sessionStorage.getItem(this.userKey) ?? localStorage.getItem(this.userKey);

    if (!usuarioGuardado) {
      return null;
    }

    let usuario: Partial<UsuarioActual>;
    try {
      usuario = JSON.parse(usuarioGuardado);
      if (!usuario || typeof usuario !== 'object') return null;
    } catch {
      this.limpiarSesionLocal();
      return null;
    }

    return {
      nombre: usuario.nombre ?? '',
      email: usuario.email ?? '',
      rol: usuario.rol,
      roles: this.normalizarRoles(usuario.roles ?? usuario.rol ?? 'PRENSA'),
    };
  }

  estaAutenticado(): boolean {
    return !!this.obtenerUsuarioActual();
  }

  tieneRol(rolesPermitidos: RolUsuario[]): boolean {
    const usuario = this.obtenerUsuarioActual();

    if (!usuario) {
      return false;
    }

    return (
      usuario.roles.includes('SUPER_ADMIN') ||
      rolesPermitidos.some((rol) => usuario.roles.includes(rol))
    );
  }

  logout(): void {
    this.http.post<void>(`${this.apiUrl}/logout`, null, { withCredentials: true }).subscribe({
      error: () => undefined,
    });
    this.limpiarSesionLocal();
  }

  private guardarSesion(response: LoginResponse): void {
    // El JWT viaja en cookie HttpOnly. Se elimina cualquier token heredado del navegador.
    localStorage.removeItem(this.legacyTokenKey);
    sessionStorage.removeItem(this.legacyTokenKey);
    this.guardarUsuario(response);
  }

  private guardarUsuario(
    usuario: Pick<UsuarioActual, 'nombre' | 'email' | 'rol'> & { roles?: RolUsuario[] },
  ): void {
    const roles = this.normalizarRoles(usuario.roles ?? usuario.rol ?? 'PRENSA');

    localStorage.removeItem(this.userKey);
    sessionStorage.setItem(
      this.userKey,
      JSON.stringify({
        nombre: usuario.nombre,
        email: usuario.email,
        rol: usuario.rol,
        roles,
      }),
    );
  }

  private limpiarSesionLocal(): void {
    localStorage.removeItem(this.legacyTokenKey);
    sessionStorage.removeItem(this.legacyTokenKey);
    localStorage.removeItem(this.userKey);
    sessionStorage.removeItem(this.userKey);
  }

  private normalizarRoles(roles: RolUsuario[] | string): RolUsuario[] {
    const rolesComoArray = Array.isArray(roles) ? roles : roles.split(',').map((rol) => rol.trim());

    return rolesComoArray
      .map((rol) => {
        if (rol === 'ADMIN' || rol === 'ROLE_ADMIN') {
          return 'SUPER_ADMIN';
        }

        return rol.replace('ROLE_', '') as RolUsuario;
      })
      .filter((rol) => rol === 'SUPER_ADMIN' || rol === 'PRENSA' || rol === 'HACIENDA');
  }
}
