import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { RolUsuario } from '../../../models/auth.model';
import { AuthService } from '../../../services/auth.service';

type AdminNavItem = {
  texto: string;
  ruta: string;
  roles: RolUsuario[];
  exacta?: boolean;
};

@Component({
  selector: 'app-admin-layout',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  templateUrl: './admin-layout.html',
  styleUrl: './admin-layout.css',
})
export class AdminLayout {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  usuario = this.authService.obtenerUsuarioActual();

  links: AdminNavItem[] = [
    {
      texto: 'Dashboard',
      ruta: '/admin',
      roles: ['PRENSA'],
      exacta: true,
    },
    {
      texto: 'Noticias',
      ruta: '/admin/noticias',
      roles: ['PRENSA'],
    },
    {
      texto: 'Nueva noticia',
      ruta: '/admin/noticias/nueva',
      roles: ['PRENSA'],
    },
    {
      texto: 'Usuarios y roles',
      ruta: '/admin/usuarios',
      roles: ['SUPER_ADMIN'],
    },
    {
      texto: 'Mi cuenta',
      ruta: '/admin/mi-cuenta',
      roles: ['SUPER_ADMIN', 'PRENSA'],
    },
  ];

  puedeVer(roles: RolUsuario[]): boolean {
    return this.authService.tieneRol(roles);
  }

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/admin/login']);
  }
}
