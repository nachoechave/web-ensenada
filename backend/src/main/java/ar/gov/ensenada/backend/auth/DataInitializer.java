package ar.gov.ensenada.backend.auth;

import org.springframework.boot.CommandLineRunner;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.stereotype.Component;

import java.util.Set;

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

        var adminExistente = usuarioRepository.findByEmailIgnoreCase(email);

        if (adminExistente.isPresent()) {
            Usuario admin = adminExistente.get();
            boolean actualizado = false;

            if (!email.equals(admin.getEmail())) {
                admin.setEmail(email);
                actualizado = true;
            }

            if (!admin.getRoles().contains(RolUsuario.SUPER_ADMIN)) {
                admin.setRoles(Set.of(RolUsuario.SUPER_ADMIN));
                actualizado = true;
            }

            if (!admin.isActivo()) {
                admin.setActivo(true);
                actualizado = true;
            }

            if (actualizado) {
                usuarioRepository.save(admin);
            }

            return;
        }

        Usuario admin = new Usuario(
                "Administrador",
                "admin@ensenada.gov.ar",
                passwordEncoder.encode("admin123"),
                Set.of(RolUsuario.SUPER_ADMIN),
                true
        );

        usuarioRepository.save(admin);

        System.out.println("Usuario superadmin creado:");
        System.out.println("Email: admin@ensenada.gov.ar");
        System.out.println("Password: admin123");
    }
}
