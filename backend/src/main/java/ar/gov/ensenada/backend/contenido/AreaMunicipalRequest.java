package ar.gov.ensenada.backend.contenido;

import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;
import jakarta.validation.constraints.Size;

public record AreaMunicipalRequest(
        @NotBlank String nombre,
        @NotBlank @Size(max = 700) String descripcion,
        @NotBlank String telefono,
        @Email String email,
        @NotBlank String direccion,
        @NotBlank String horario
) {
}
