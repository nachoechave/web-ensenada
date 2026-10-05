import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { environment } from '../../environments/environment';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  if (!esApiPropia(req.url)) {
    return next(req);
  }

  const router = inject(Router);

  // La autenticación principal usa una cookie HttpOnly emitida por el backend.
  // withCredentials también permite desarrollo/producción con API en origen permitido.
  return next(req.clone({ withCredentials: true })).pipe(
    catchError((error: unknown) => {
      if (error instanceof HttpErrorResponse && error.status === 401 && !esLogin(req.url)) {
        limpiarSesionLocal();
        void router.navigate(['/admin/login']);
      }
      return throwError(() => error);
    }),
  );
};

function esApiPropia(url: string): boolean {
  try {
    const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
    const parsedUrl = new URL(url, origin);
    const api = new URL(environment.apiUrl, origin);
    return parsedUrl.origin === api.origin && parsedUrl.pathname.startsWith('/api/');
  } catch {
    return false;
  }
}

function esLogin(url: string): boolean {
  try {
    const origin = typeof window !== 'undefined' ? window.location.origin : 'http://localhost';
    const parsedUrl = new URL(url, origin);
    return parsedUrl.pathname.endsWith('/api/auth/login');
  } catch {
    return false;
  }
}

function limpiarSesionLocal(): void {
  if (typeof window === 'undefined') return;
  localStorage.removeItem('admin-token');
  sessionStorage.removeItem('admin-token');
  localStorage.removeItem('admin-user');
  sessionStorage.removeItem('admin-user');
}
