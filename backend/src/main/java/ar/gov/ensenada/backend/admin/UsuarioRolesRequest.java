package ar.gov.ensenada.backend.admin;

import ar.gov.ensenada.backend.auth.RolUsuario;
import jakarta.validation.constraints.NotEmpty;

import java.util.Set;

public record UsuarioRolesRequest(
        @NotEmpty(message = "Debe asignarse al menos un rol")
        Set<RolUsuario> roles
) {
}
