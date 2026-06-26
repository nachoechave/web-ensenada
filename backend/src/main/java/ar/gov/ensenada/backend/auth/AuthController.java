package ar.gov.ensenada.backend.auth;

import jakarta.validation.Valid;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.web.bind.annotation.*;

@RestController
@RequestMapping("/api/auth")
public class AuthController {

    private final AuthenticationManager authenticationManager;
    private final JwtService jwtService;
    private final UsuarioRepository usuarioRepository;

    public AuthController(
            AuthenticationManager authenticationManager,
            JwtService jwtService,
            UsuarioRepository usuarioRepository
    ) {
        this.authenticationManager = authenticationManager;
        this.jwtService = jwtService;
        this.usuarioRepository = usuarioRepository;
    }

    @PostMapping("/login")
    public LoginResponse login(@Valid @RequestBody LoginRequest request) {
        Authentication authentication = authenticationManager.authenticate(
                new UsernamePasswordAuthenticationToken(
                        request.email(),
                        request.password()
                )
        );

        String token = jwtService.generarToken(authentication);

        Usuario usuario = usuarioRepository.findByEmail(request.email())
                .orElseThrow();

        return new LoginResponse(
                token,
                usuario.getNombre(),
                usuario.getEmail(),
                usuario.getRol()
        );
    }

    @GetMapping("/me")
    public UsuarioResponse me() {
        String email = SecurityContextHolder.getContext()
                .getAuthentication()
                .getName();

        Usuario usuario = usuarioRepository.findByEmail(email)
                .orElseThrow();

        return new UsuarioResponse(
                usuario.getNombre(),
                usuario.getEmail(),
                usuario.getRol()
        );
    }
}