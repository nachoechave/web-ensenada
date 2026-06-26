package ar.gov.ensenada.backend.auth;

public record LoginResponse(
        String token,
        String nombre,
        String email,
        String rol
) {
}