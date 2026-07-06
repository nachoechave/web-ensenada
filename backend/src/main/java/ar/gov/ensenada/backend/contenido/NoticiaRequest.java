package ar.gov.ensenada.backend.contenido;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;

public record NoticiaRequest(
        @NotBlank String titulo,
        @NotBlank String bajada,
        @NotBlank String contenido,
        @NotBlank String imagen,
        @NotBlank String categoria,
        @NotBlank String fecha,
        @NotNull EstadoPublicacion estado,
        boolean destacada
) {
}
