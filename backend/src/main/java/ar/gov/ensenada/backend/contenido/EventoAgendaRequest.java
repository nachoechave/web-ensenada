package ar.gov.ensenada.backend.contenido;

import jakarta.validation.constraints.NotBlank;

public record EventoAgendaRequest(
        @NotBlank String fecha,
        @NotBlank String horario,
        @NotBlank String titulo,
        @NotBlank String lugar,
        @NotBlank String categoria,
        @NotBlank String descripcion
) {
}
