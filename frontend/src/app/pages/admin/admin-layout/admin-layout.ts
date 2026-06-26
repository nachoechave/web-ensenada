import { Component, inject } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';

import { AuthService } from '../../../services/auth.service';

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

  logout(): void {
    this.authService.logout();
    this.router.navigate(['/admin/login']);
  }
}