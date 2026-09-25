package ar.gov.ensenada.backend.auth;

import com.nimbusds.jose.jwk.source.ImmutableSecret;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.http.HttpMethod;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.DisabledException;
import org.springframework.security.authentication.ProviderManager;
import org.springframework.security.authentication.dao.DaoAuthenticationProvider;
import org.springframework.security.config.Customizer;
import org.springframework.security.config.annotation.method.configuration.EnableMethodSecurity;
import org.springframework.security.config.annotation.web.builders.HttpSecurity;
import org.springframework.security.config.http.SessionCreationPolicy;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.userdetails.*;
import org.springframework.security.crypto.bcrypt.BCryptPasswordEncoder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.security.oauth2.jose.jws.MacAlgorithm;
import org.springframework.security.oauth2.jwt.*;
import org.springframework.security.oauth2.server.resource.authentication.JwtAuthenticationConverter;
import org.springframework.security.web.SecurityFilterChain;
import org.springframework.web.cors.*;

import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.util.List;

@Configuration
@EnableMethodSecurity
public class SecurityConfig {

    @Value("${app.jwt.secret}")
    private String jwtSecret;

    @Value("${app.cors.allowed-origins}")
    private List<String> allowedOrigins;

    @Value("${app.login-rate-limit.attempts}") private int loginAttempts;
    @Value("${app.login-rate-limit.window-seconds}") private long loginWindowSeconds;
    @Value("${app.login-rate-limit.max-ips}") private int loginMaxIps;

    @Bean
    SecurityFilterChain securityFilterChain(HttpSecurity http, UsuarioRepository repository) throws Exception {
        return http
                .addFilterBefore(new LoginRateLimitFilter(new LoginRateLimiter(loginAttempts, loginWindowSeconds, loginMaxIps, java.time.Clock.systemUTC())),
                    org.springframework.security.web.authentication.UsernamePasswordAuthenticationFilter.class)
                .cors(Customizer.withDefaults())
                .csrf(csrf -> csrf.disable())
                .sessionManagement(session ->
                        session.sessionCreationPolicy(SessionCreationPolicy.STATELESS)
                )
                .authorizeHttpRequests(auth -> auth
                        .requestMatchers(HttpMethod.OPTIONS, "/**").permitAll()
                        .requestMatchers(HttpMethod.GET, "/actuator/health", "/actuator/health/**").permitAll()
                        .requestMatchers(HttpMethod.POST, "/api/auth/login").permitAll()
                        .requestMatchers(HttpMethod.GET, "/api/auth/me").authenticated()
                        .requestMatchers(HttpMethod.PUT, "/api/auth/me").authenticated()
                        .requestMatchers(HttpMethod.PUT, "/api/auth/me/password").authenticated()
                        .requestMatchers("/api/admin/usuarios/**").hasRole("SUPER_ADMIN")
                        .requestMatchers("/api/admin/site-content/**").hasRole("SUPER_ADMIN")
                        .requestMatchers("/api/admin/archivos/sitio").hasRole("SUPER_ADMIN")
                        .requestMatchers("/api/admin/hacienda/**").hasAnyRole("SUPER_ADMIN", "HACIENDA")
                        .requestMatchers(HttpMethod.GET, "/api/hacienda/**", "/uploads/hacienda/*").permitAll()
                        .requestMatchers("/api/admin/noticias/**").hasAnyRole("SUPER_ADMIN", "PRENSA")
                        .requestMatchers("/api/admin/archivos/noticias").hasAnyRole("SUPER_ADMIN", "PRENSA")
                        .requestMatchers(HttpMethod.GET, "/api/site-content", "/uploads/sitio/*.png", "/uploads/sitio/*.jpg").permitAll()
                        .requestMatchers(HttpMethod.GET, "/uploads/noticias/*.png", "/uploads/noticias/*.jpg").permitAll()
                        .requestMatchers("/api/admin/**").denyAll()
                        .requestMatchers(HttpMethod.GET, "/api/noticias/**").permitAll()
                        .anyRequest().denyAll()
                )
                .oauth2ResourceServer(oauth2 ->
                        oauth2.jwt(jwt -> jwt.jwtAuthenticationConverter(jwtAuthenticationConverter(repository)))
                )
                .build();
    }

    @Bean
    UserDetailsService userDetailsService(UsuarioRepository usuarioRepository) {
        return email -> {
            Usuario usuario = usuarioRepository.findByEmailIgnoreCase(email.trim().toLowerCase())
                    .orElseThrow(() -> new UsernameNotFoundException("Usuario no encontrado"));

            if (!usuario.isActivo()) {
                throw new DisabledException("Usuario desactivado");
            }

            List<SimpleGrantedAuthority> authorities = usuario.getRoles()
                    .stream()
                    .map(rol -> new SimpleGrantedAuthority("ROLE_" + rol.name()))
                    .toList();

            return new User(usuario.getEmail(), usuario.getPassword(), authorities);
        };
    }

    @Bean
    AuthenticationManager authenticationManager(
            UserDetailsService userDetailsService,
            PasswordEncoder passwordEncoder
    ) {
        DaoAuthenticationProvider provider = new DaoAuthenticationProvider(userDetailsService);
        provider.setPasswordEncoder(passwordEncoder);
        return new ProviderManager(provider);
    }

    @Bean
    PasswordEncoder passwordEncoder() {
        return new BCryptPasswordEncoder();
    }

    @Bean
    JwtEncoder jwtEncoder() {
        SecretKeySpec secretKey = new SecretKeySpec(
                jwtSecret.getBytes(StandardCharsets.UTF_8),
                "HmacSHA256"
        );

        return new NimbusJwtEncoder(new ImmutableSecret<>(secretKey));
    }

    @Bean
    JwtDecoder jwtDecoder() {
        SecretKeySpec secretKey = new SecretKeySpec(
                jwtSecret.getBytes(StandardCharsets.UTF_8),
                "HmacSHA256"
        );

        if (jwtSecret.getBytes(StandardCharsets.UTF_8).length < 32) throw new IllegalArgumentException("JWT_SECRET requiere al menos 32 bytes");
        NimbusJwtDecoder decoder = NimbusJwtDecoder.withSecretKey(secretKey).macAlgorithm(MacAlgorithm.HS256).build();
        decoder.setJwtValidator(JwtValidators.createDefaultWithIssuer("web-ensenada"));
        return decoder;
    }

    @Bean
    JwtAuthenticationConverter jwtAuthenticationConverter(UsuarioRepository repository) {
        JwtAuthenticationConverter converter = new JwtAuthenticationConverter();
        converter.setJwtGrantedAuthoritiesConverter(jwt -> {
            Usuario usuario = repository.findByEmailIgnoreCase(jwt.getSubject())
                .filter(Usuario::isActivo)
                .orElseThrow(() -> new org.springframework.security.oauth2.core.OAuth2AuthenticationException("invalid_token"));

            Object tokenVersionClaim = jwt.getClaim("tokenVersion");
            long tokenVersion = tokenVersionClaim instanceof Number number ? number.longValue() : -1;
            if (tokenVersion != usuario.getTokenVersion()) {
                throw new org.springframework.security.oauth2.core.OAuth2AuthenticationException("invalid_token");
            }

            return usuario.getRoles().stream()
                .map(rol -> (org.springframework.security.core.GrantedAuthority) new SimpleGrantedAuthority("ROLE_" + rol.name())).toList();
        });

        return converter;
    }

    @Bean
    CorsConfigurationSource corsConfigurationSource() {
        CorsConfiguration configuration = new CorsConfiguration();

        configuration.setAllowedOrigins(allowedOrigins);
        configuration.setAllowedMethods(List.of("GET", "POST", "PUT", "DELETE", "OPTIONS"));
        configuration.setAllowedHeaders(List.of("Authorization", "Content-Type"));
        configuration.setAllowCredentials(false);

        UrlBasedCorsConfigurationSource source = new UrlBasedCorsConfigurationSource();
        source.registerCorsConfiguration("/**", configuration);

        return source;
    }
}
