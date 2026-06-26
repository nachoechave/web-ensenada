import { ChangeDetectorRef, Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { finalize } from 'rxjs';

import { AuthService } from '../../../services/auth.service';

@Component({
  selector: 'app-admin-login',
  imports: [FormsModule],
  templateUrl: './admin-login.html',
  styleUrl: './admin-login.css',
})
export class AdminLogin {
  private readonly authService = inject(AuthService);
  private readonly router = inject(Router);
  private readonly cdr = inject(ChangeDetectorRef);

  email = 'admin@ensenada.gov.ar';
  password = '';

  cargando = false;
  error = '';

  private timeoutLogin?: ReturnType<typeof setTimeout>;

  login(): void {
    if (this.cargando) {
      return;
    }

    this.error = '';
    this.cargando = true;
    this.cdr.detectChanges();

    localStorage.removeItem('admin-token');
    localStorage.removeItem('admin-user');

    this.timeoutLogin = setTimeout(() => {
      if (this.cargando) {
        this.cargando = false;
        this.error = 'No se pudo iniciar sesión. Revisá los datos o intentá nuevamente.';
        this.cdr.detectChanges();
      }
    }, 8000);

    this.authService
      .login({
        email: this.email.trim(),
        password: this.password,
      })
      .pipe(
        finalize(() => {
          this.cargando = false;

          if (this.timeoutLogin) {
            clearTimeout(this.timeoutLogin);
          }

          this.cdr.detectChanges();
        }),
      )
      .subscribe({
        next: () => {
          this.router.navigate(['/admin']);
        },
        error: () => {
          this.error = 'Email o contraseña incorrectos.';
          this.cdr.detectChanges();
        },
      });
  }
}