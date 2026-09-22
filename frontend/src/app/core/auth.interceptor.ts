import { HttpInterceptorFn } from '@angular/common/http';
import { environment } from '../../environments/environment';

export const authInterceptor: HttpInterceptorFn = (req, next) => {
  if (req.url.includes('/api/auth/login')) {
    return next(req);
  }

  const token = localStorage.getItem('admin-token');

  if (!token || !requiereToken(req.url)) {
    return next(req);
  }

  const requestConToken = req.clone({
    setHeaders: {
      Authorization: `Bearer ${token}`,
    },
  });

  return next(requestConToken);
};

function requiereToken(url: string): boolean {
  try {
    const parsedUrl = new URL(url, window.location.origin);
    const api = new URL(environment.apiUrl, window.location.origin);
    if (parsedUrl.origin !== api.origin) return false;
    return (
      parsedUrl.pathname.startsWith('/api/admin/') ||
      parsedUrl.pathname === '/api/admin' ||
      parsedUrl.pathname.startsWith('/api/auth/me')
    );
  } catch {
    return false;
  }
}
