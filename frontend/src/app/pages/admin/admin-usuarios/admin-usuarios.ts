import { Component, inject } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { RolUsuario } from '../../../models/auth.model';
import { UsuarioAdmin } from '../../../models/usuario-admin.model';
import {
  UsuarioCrearRequest,
  UsuariosAdminService,
} from '../../../services/usuarios-admin.service';

@Component({
  selector: 'app-admin-usuarios',
  imports: [FormsModule],
  templateUrl: './admin-usuarios.html',
  styleUrl: './admin-usuarios.css',
})
export class AdminUsuarios {
  private readonly usuariosService = inject(UsuariosAdminService);

  rolesDisponibles = this.usuariosService.rolesDisponibles;
  usuarios: UsuarioAdmin[] = [];
  cargando = false;
  error = '';

  usuario: UsuarioCrearRequest = {
    nombre: '',
    email: '',
    password: '',
    roles: ['PRENSA'],
    activo: true,
  };

  constructor() {
    this.cargarUsuarios();
  }

  cargarUsuarios(): void {
    this.cargando = true;
    this.error = '';

    this.usuariosService.obtenerUsuarios().subscribe({
      next: (usuarios) => {
        this.usuarios = usuarios;
        this.cargando = false;
      },
      error: () => {
        this.error = 'No se pudieron cargar los usuarios.';
        this.cargando = false;
      },
    });
  }

  guardar(): void {
    this.usuariosService.guardar(this.usuario).subscribe({
      next: () => {
        this.usuario = {
          nombre: '',
          email: '',
          password: '',
          roles: ['PRENSA'],
          activo: true,
        };
        this.cargarUsuarios();
      },
      error: () => {
        this.error = 'No se pudo crear el usuario.';
      },
    });
  }

  alternarRol(usuario: UsuarioAdmin, rol: RolUsuario): void {
    const tieneRol = usuario.roles.includes(rol);
    const roles = tieneRol
      ? usuario.roles.filter((rolActual) => rolActual !== rol)
      : [...usuario.roles, rol];

    if (roles.length === 0) {
      return;
    }

    this.usuariosService.actualizarRoles(usuario.id, roles).subscribe({
      next: () => this.cargarUsuarios(),
      error: () => {
        this.error = 'No se pudieron actualizar los roles.';
      },
    });
  }

  alternarRolNuevo(rol: RolUsuario): void {
    const tieneRol = this.usuario.roles.includes(rol);

    this.usuario.roles = tieneRol
      ? this.usuario.roles.filter((rolActual) => rolActual !== rol)
      : [...this.usuario.roles, rol];

    if (this.usuario.roles.length === 0) {
      this.usuario.roles = ['PRENSA'];
    }
  }
}
