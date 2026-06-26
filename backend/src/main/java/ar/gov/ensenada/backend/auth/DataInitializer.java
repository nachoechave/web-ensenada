package ar.gov.ensenada.backend.auth;

import org.springframework.boot.CommandLineRunner;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Component;

@Component
public class DataInitializer implements CommandLineRunner {

    private final UsuarioRepository usuarioRepository;
    private final PasswordEncoder passwordEncoder;

    public DataInitializer(
            UsuarioRepository usuarioRepository,
            PasswordEncoder passwordEncoder
    ) {
        this.usuarioRepository = usuarioRepository;
        this.passwordEncoder = passwordEncoder;
    }

    @Override
    public void run(String... args) {
        String email = "admin@ensenada.gov.ar";

        if (usuarioRepository.findByEmail(email).isPresent()) {
            return;
        }

        Usuario admin = new Usuario(
                "Administrador",
                email,
                passwordEncoder.encode("admin123"),
                "ADMIN",
                true
        );

        usuarioRepository.save(admin);

        System.out.println("Usuario admin creado:");
        System.out.println("Email: admin@ensenada.gov.ar");
        System.out.println("Password: admin123");
    }
}