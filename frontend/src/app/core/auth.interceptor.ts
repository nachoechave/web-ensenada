import { HttpInterceptorFn } from '@angular/common/http';
import { environment } from '../../environments/environment';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  if (!esApiPropia(req.url)) {
    return next(req);
  }

  // La autenticación principal usa una cookie HttpOnly emitida por el backend.
  // withCredentials también permite desarrollo/producción con API en origen permitido.
  return next(req.clone({ withCredentials: true }));
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
