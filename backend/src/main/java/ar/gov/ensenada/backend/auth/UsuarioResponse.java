package ar.gov.ensenada.backend.auth;

import java.util.Set;

public record UsuarioResponse(
        String nombre,
        String email,
        String rol,
        Set<RolUsuario> roles
) {
}
