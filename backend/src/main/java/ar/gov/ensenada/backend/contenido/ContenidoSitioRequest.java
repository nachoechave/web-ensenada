package ar.gov.ensenada.backend.contenido;

import jakarta.validation.constraints.Email;
import jakarta.validation.constraints.NotBlank;

public record ContenidoSitioRequest(
        @NotBlank String heroTitulo,
        @NotBlank String heroBajada,
        @NotBlank String footerDescripcion,
        @Email @NotBlank String emailContacto,
        @NotBlank String telefonoContacto,
        @NotBlank String direccionMunicipio,
        @NotBlank String horarioAtencion,
        @NotBlank String textoTramites,
        @NotBlank String notaInstitucionalContacto
) {
}
