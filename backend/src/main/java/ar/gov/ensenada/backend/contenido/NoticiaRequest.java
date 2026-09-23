package ar.gov.ensenada.backend.contenido;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;
import jakarta.validation.constraints.Pattern;

public record NoticiaRequest(
        @NotBlank @Size(max=255) String titulo,
        @NotBlank @Size(max=500) String bajada,
        @NotBlank @Size(max=60000) String contenido,
        @NotBlank @Size(max=1000) @Pattern(regexp="^(https://[^\\s]+|/uploads/noticias/[a-zA-Z0-9._-]+|/assets/[a-zA-Z0-9/._-]+)$") String imagen,
        @NotBlank @Size(max=255) String categoria,
        @NotNull java.time.LocalDate fechaPublicacion,
        @NotNull EstadoPublicacion estado,
        boolean destacada
) {
}
