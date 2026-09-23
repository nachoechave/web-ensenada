import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { AuthService } from '../services/auth.service';

export const adminGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);

  if (authService.estaAutenticado() && authService.tieneRol(['SUPER_ADMIN', 'PRENSA', 'HACIENDA'])) {
    return true;
  }

  return router.createUrlTree(['/admin/login']);
};
