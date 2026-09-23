package ar.gov.ensenada.backend.admin;

import ar.gov.ensenada.backend.auth.RolUsuario;

import java.util.Set;

public record UsuarioAdminResponse(
        Long id,
        String nombre,
        String email,
        Set<RolUsuario> roles,
        boolean activo
) {
}
