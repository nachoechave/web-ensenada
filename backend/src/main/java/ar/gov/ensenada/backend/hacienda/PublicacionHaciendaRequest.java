package ar.gov.ensenada.backend.hacienda;
import ar.gov.ensenada.backend.contenido.EstadoPublicacion;
import jakarta.validation.constraints.*;
import java.time.LocalDate;

public record PublicacionHaciendaRequest(
    @NotBlank @Size(max=255) String titulo,
    @NotNull @Size(max=10000) String descripcion,
    @NotNull TipoPublicacionHacienda tipo,
    @NotNull LocalDate fechaPublicacion,
    @NotNull EstadoPublicacion estado
) {}
