package ar.gov.ensenada.backend.auth;

import java.util.Set;

public record LoginResponse(
        String token,
        String nombre,
        String email,
        String rol,
        Set<RolUsuario> roles
) {
}
