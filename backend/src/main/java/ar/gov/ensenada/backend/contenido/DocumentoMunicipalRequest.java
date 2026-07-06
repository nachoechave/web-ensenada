package ar.gov.ensenada.backend.contenido;

import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.NotNull;

public record DocumentoMunicipalRequest(
        @NotBlank String titulo,
        @NotBlank String fecha,
        @NotBlank String hora,
        @NotNull Integer anio,
        String descripcion,
        @NotBlank String archivoUrl
) {
}
