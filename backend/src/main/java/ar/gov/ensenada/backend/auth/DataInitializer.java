package ar.gov.ensenada.backend.auth;

import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.boot.CommandLineRunner;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Component;

import java.util.Set;

@Component
public class DataInitializer implements CommandLineRunner {

    private static final Logger LOGGER = LoggerFactory.getLogger(DataInitializer.class);

    private final UsuarioRepository usuarioRepository;
    private final PasswordEncoder passwordEncoder;
    private final String adminEmail;
    private final String adminPassword;

    public DataInitializer(
            UsuarioRepository usuarioRepository,
            PasswordEncoder passwordEncoder,
            @Value("${APP_ADMIN_EMAIL:}") String adminEmail,
            @Value("${APP_ADMIN_PASSWORD:}") String adminPassword
    ) {
        this.usuarioRepository = usuarioRepository;
        this.passwordEncoder = passwordEncoder;
        this.adminEmail = adminEmail.trim();
        this.adminPassword = adminPassword;
    }

    @Override
    public void run(String... args) {
        if (adminEmail.isBlank() || adminPassword.isBlank()) {
            LOGGER.info("No se configuró un administrador inicial; se omite su creación automática");
            return;
        }

        var adminExistente = usuarioRepository.findByEmailIgnoreCase(adminEmail);

        if (adminExistente.isPresent()) { return; }
        if (adminPassword.length() < 12 || adminPassword.length() > 72) {
            throw new IllegalArgumentException("APP_ADMIN_PASSWORD debe tener entre 12 y 72 caracteres");
        }
        Usuario admin = new Usuario(
                "Administrador",
                adminEmail,
                passwordEncoder.encode(adminPassword),
                Set.of(RolUsuario.SUPER_ADMIN),
                true
        );

        usuarioRepository.save(admin);

        LOGGER.info("Usuario superadministrador inicial creado");
    }
}
