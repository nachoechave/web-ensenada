package ar.gov.ensenada.backend.admin;

import ar.gov.ensenada.backend.auth.RolUsuario;
import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotEmpty;

import java.util.Set;

public record UsuarioCrearRequest(
        @NotBlank(message = "El nombre es obligatorio")
        String nombre,

        @Email(message = "El email no es válido")
        @NotBlank(message = "El email es obligatorio")
        String email,

        @NotBlank(message = "La contraseña es obligatoria")
        String password,

        @NotEmpty(message = "Debe asignarse al menos un rol")
        Set<RolUsuario> roles,

        boolean activo
) {
}
