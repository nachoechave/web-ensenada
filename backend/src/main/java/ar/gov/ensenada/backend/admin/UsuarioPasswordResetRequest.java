package ar.gov.ensenada.backend.admin;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

public record UsuarioPasswordResetRequest(
        @NotBlank
        @Size(min = 12, max = 72)
        String password
) {
}
