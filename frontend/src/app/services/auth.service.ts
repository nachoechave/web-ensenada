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
  private readonly tokenKey = 'admin-token';
  private readonly userKey = 'admin-user';

  login(credenciales: LoginRequest): Observable<LoginResponse> {
    return this.http
      .post<LoginResponse>(`${this.apiUrl}/login`, credenciales)
      .pipe(tap((response) => this.guardarSesion(response)));
  }

  obtenerPerfil(): Observable<UsuarioActual> {
    return this.http
      .get<UsuarioActual>(`${this.apiUrl}/me`)
      .pipe(tap((usuario) => this.guardarUsuario(usuario)));
  }

  actualizarPerfil(perfil: ActualizarPerfilRequest): Observable<LoginResponse> {
    return this.http
      .put<LoginResponse>(`${this.apiUrl}/me`, perfil)
      .pipe(tap((response) => this.guardarSesion(response)));
  }

  cambiarPassword(passwords: CambiarPasswordRequest): Observable<void> {
    return this.http.put<void>(`${this.apiUrl}/me/password`, passwords);
  }

  obtenerUsuarioActual(): UsuarioActual | null {
    const usuarioGuardado = localStorage.getItem(this.userKey);

    if (!usuarioGuardado) {
      return null;
    }

    let usuario: Partial<UsuarioActual>;
    try {
      usuario = JSON.parse(usuarioGuardado);
      if (!usuario || typeof usuario !== 'object') return null;
    } catch {
      this.logout();
      return null;
    }

    return {
      nombre: usuario.nombre ?? '',
      email: usuario.email ?? '',
      rol: usuario.rol,
      roles: this.normalizarRoles(usuario.roles ?? usuario.rol ?? 'PRENSA'),
    };
  }

  obtenerToken(): string | null {
    return localStorage.getItem(this.tokenKey);
  }

  estaAutenticado(): boolean {
    return !!this.obtenerToken();
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
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.userKey);
  }

  private guardarSesion(response: LoginResponse): void {
    localStorage.setItem(this.tokenKey, response.token);
    this.guardarUsuario(response);
  }

  private guardarUsuario(
    usuario: Pick<UsuarioActual, 'nombre' | 'email' | 'rol'> & { roles?: RolUsuario[] },
  ): void {
    const roles = this.normalizarRoles(usuario.roles ?? usuario.rol ?? 'PRENSA');

    localStorage.setItem(
      this.userKey,
      JSON.stringify({
        nombre: usuario.nombre,
        email: usuario.email,
        rol: usuario.rol,
        roles,
      }),
    );
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
