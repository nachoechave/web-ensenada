import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, tap } from 'rxjs';

import { LoginRequest, LoginResponse, UsuarioActual } from '../models/auth.model';

@Injectable({
  providedIn: 'root',
})
export class AuthService {
  private readonly http = inject(HttpClient);

  private readonly apiUrl = 'http://localhost:8080/api/auth';
  private readonly tokenKey = 'admin-token';
  private readonly userKey = 'admin-user';

  login(credenciales: LoginRequest): Observable<LoginResponse> {
    return this.http.post<LoginResponse>(`${this.apiUrl}/login`, credenciales).pipe(
      tap((response) => {
        localStorage.setItem(this.tokenKey, response.token);

        localStorage.setItem(
          this.userKey,
          JSON.stringify({
            nombre: response.nombre,
            email: response.email,
            rol: response.rol,
          }),
        );
      }),
    );
  }

  obtenerUsuarioActual(): UsuarioActual | null {
    const usuarioGuardado = localStorage.getItem(this.userKey);

    if (!usuarioGuardado) {
      return null;
    }

    return JSON.parse(usuarioGuardado) as UsuarioActual;
  }

  obtenerToken(): string | null {
    return localStorage.getItem(this.tokenKey);
  }

  estaAutenticado(): boolean {
    return !!this.obtenerToken();
  }

  logout(): void {
    localStorage.removeItem(this.tokenKey);
    localStorage.removeItem(this.userKey);
  }
}