package ar.gov.ensenada.backend.auth;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.security.core.Authentication;
import org.springframework.security.oauth2.jose.jws.MacAlgorithm;
import org.springframework.security.oauth2.jwt.*;
import org.springframework.stereotype.Service;

import java.time.Instant;
import java.time.temporal.ChronoUnit;

@Service
public class JwtService {

    private final JwtEncoder jwtEncoder;
    private final long expirationMinutes;

    public JwtService(
            JwtEncoder jwtEncoder,
            @Value("${app.jwt.expiration-minutes}") long expirationMinutes
    ) {
        this.jwtEncoder = jwtEncoder;
        this.expirationMinutes = expirationMinutes;
    }

    public String generarToken(Authentication authentication) {
        Instant ahora = Instant.now();

        String rol = authentication.getAuthorities()
                .stream()
                .findFirst()
                .map(Object::toString)
                .orElse("ROLE_PERIODISTA");

        JwtClaimsSet claims = JwtClaimsSet.builder()
                .issuer("web-ensenada")
                .issuedAt(ahora)
                .expiresAt(ahora.plus(expirationMinutes, ChronoUnit.MINUTES))
                .subject(authentication.getName())
                .claim("rol", rol)
                .build();

        JwsHeader header = JwsHeader.with(MacAlgorithm.HS256).build();

        return jwtEncoder.encode(JwtEncoderParameters.from(header, claims)).getTokenValue();
    }
}