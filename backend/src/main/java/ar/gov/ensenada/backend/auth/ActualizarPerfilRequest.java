package ar.gov.ensenada.backend.auth;

import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;

public record ActualizarPerfilRequest(
        @NotBlank(message = "El nombre es obligatorio")
        String nombre,

        @Email(message = "El email no es válido")
        @NotBlank(message = "El email es obligatorio")
        String email
) {
}
