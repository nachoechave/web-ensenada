import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize, timeout } from 'rxjs';

import { AuthService } from '../../../services/auth.service';

@Component({
  selector: 'app-admin-mi-cuenta',
  imports: [FormsModule],
  templateUrl: './admin-mi-cuenta.html',
  styleUrl: './admin-mi-cuenta.css',
})
export class AdminMiCuenta {
  private readonly cdr = inject(ChangeDetectorRef);
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);

  perfil = {
    nombre: '',
    email: '',
  };

  passwords = {
    actual: '',
    nueva: '',
    repetir: '',
  };

  cargandoPerfil = false;
  guardandoPerfil = false;
  guardandoPassword = false;
  mensajePerfil = '';
  errorPerfil = '';
  mensajePassword = '';
  errorPassword = '';

  constructor() {
    const usuario = this.authService.obtenerUsuarioActual();

    if (usuario) {
      this.perfil = {
        nombre: usuario.nombre,
        email: usuario.email,
      };
    }

    this.cargarPerfil();
  }

  cargarPerfil(): void {
    this.cargandoPerfil = true;

    this.authService
      .obtenerPerfil()
      .pipe(
        timeout(15000),
        finalize(() => {
          this.cdr.markForCheck();
          this.cargandoPerfil = false;
        }),
      )
      .subscribe({
        next: (usuario) => {
          this.cdr.markForCheck();
          this.perfil = {
            nombre: usuario.nombre,
            email: usuario.email,
          };
        },
        error: (error) => {
          this.cdr.markForCheck();
          this.errorPerfil = this.obtenerMensajeError(
            error,
            'No se pudieron cargar los datos actuales.',
          );
        },
      });
  }

  guardarPerfil(): void {
    this.mensajePerfil = '';
    this.errorPerfil = '';
    this.guardandoPerfil = true;

    this.authService
      .actualizarPerfil(this.perfil)
      .pipe(
        timeout(15000),
        finalize(() => {
          this.cdr.markForCheck();
          this.guardandoPerfil = false;
        }),
      )
      .subscribe({
        next: () => {
          this.cdr.markForCheck();
          this.mensajePerfil = 'Tus datos se actualizaron correctamente.';
        },
        error: (error) => {
          this.cdr.markForCheck();
          this.errorPerfil = this.obtenerMensajeError(error, 'No se pudieron guardar tus datos.');
        },
      });
  }

  cambiarPassword(): void {
    this.mensajePassword = '';
    this.errorPassword = '';

    if (this.passwords.nueva !== this.passwords.repetir) {
      this.errorPassword = 'La nueva contraseña y su repetición no coinciden.';
      return;
    }

    this.guardandoPassword = true;

    this.authService
      .cambiarPassword({
        passwordActual: this.passwords.actual,
        passwordNueva: this.passwords.nueva,
      })
      .pipe(
        timeout(15000),
        finalize(() => {
          this.cdr.markForCheck();
          this.guardandoPassword = false;
        }),
      )
      .subscribe({
        next: () => {
          this.cdr.markForCheck();
          this.mensajePassword = 'Contraseña actualizada. Iniciá sesión nuevamente.';
          this.authService.logout();
          setTimeout(() => this.router.navigate(['/admin/login']), 1200);
        },
        error: (error) => {
          this.cdr.markForCheck();
          this.errorPassword = this.obtenerMensajeError(error, 'No se pudo cambiar la contraseña.');
        },
      });
  }

  private obtenerMensajeError(error: unknown, mensajePorDefecto: string): string {
    const posibleError = error as { name?: string; error?: { message?: string; detail?: string } };

    if (posibleError.name === 'TimeoutError') {
      return 'El servidor tardó demasiado en responder. Revisá que el backend esté levantado.';
    }

    return posibleError.error?.message ?? posibleError.error?.detail ?? mensajePorDefecto;
  }
}
