package ar.gov.ensenada.backend.auth;

public record UsuarioResponse(
        String nombre,
        String email,
        String rol
) {
}