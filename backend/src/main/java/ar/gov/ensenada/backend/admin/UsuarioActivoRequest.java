package ar.gov.ensenada.backend.admin;
import jakarta.validation.constraints.NotNull;
public record UsuarioActivoRequest(@NotNull Boolean activo) {}
