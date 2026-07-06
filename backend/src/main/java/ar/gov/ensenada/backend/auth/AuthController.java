package ar.gov.ensenada.backend.auth;

import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.security.authentication.AuthenticationManager;
import org.springframework.security.authentication.UsernamePasswordAuthenticationToken;
import org.springframework.security.core.Authentication;
import org.springframework.security.core.authority.SimpleGrantedAuthority;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;

@RestController
@RequestMapping("/api/auth")
public class AuthController {

    private final AuthenticationManager authenticationManager;
    private final JwtService jwtService;
    private final UsuarioRepository usuarioRepository;
    private final PasswordEncoder passwordEncoder;

    public AuthController(
            AuthenticationManager authenticationManager,
            JwtService jwtService,
            UsuarioRepository usuarioRepository,
            PasswordEncoder passwordEncoder
    ) {
        this.authenticationManager = authenticationManager;
        this.jwtService = jwtService;
        this.usuarioRepository = usuarioRepository;
        this.passwordEncoder = passwordEncoder;
    }

    @PostMapping("/login")
    public LoginResponse login(@Valid @RequestBody LoginRequest request) {
        String email = normalizarEmail(request.email());

        Authentication authentication = authenticationManager.authenticate(
                new UsernamePasswordAuthenticationToken(
                        email,
                        request.password()
                )
        );

        Usuario usuario = usuarioRepository.findByEmailIgnoreCase(email)
                .orElseThrow();

        return crearLoginResponse(usuario, authentication);
    }

    @GetMapping("/me")
    public UsuarioResponse me() {
        Usuario usuario = obtenerUsuarioAutenticado();

        return new UsuarioResponse(
                usuario.getNombre(),
                usuario.getEmail(),
                obtenerRolPrincipal(usuario),
                usuario.getRoles()
        );
    }

    @PutMapping("/me")
    public LoginResponse actualizarPerfil(@Valid @RequestBody ActualizarPerfilRequest request) {
        Usuario usuario = obtenerUsuarioAutenticado();
        String email = normalizarEmail(request.email());

        usuarioRepository.findByEmailIgnoreCase(email)
                .filter(usuarioEncontrado -> !usuarioEncontrado.getId().equals(usuario.getId()))
                .ifPresent(usuarioEncontrado -> {
                    throw new ResponseStatusException(HttpStatus.CONFLICT, "Ya existe un usuario con ese email");
                });

        usuario.setNombre(request.nombre());
        usuario.setEmail(email);

        Usuario usuarioGuardado = usuarioRepository.save(usuario);

        return crearLoginResponse(usuarioGuardado);
    }

    @PutMapping("/me/password")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void cambiarPassword(@Valid @RequestBody CambiarPasswordRequest request) {
        Usuario usuario = obtenerUsuarioAutenticado();

        if (!passwordEncoder.matches(request.passwordActual(), usuario.getPassword())) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "La contraseña actual no es correcta");
        }

        usuario.setPassword(passwordEncoder.encode(request.passwordNueva()));
        usuarioRepository.save(usuario);
    }

    private Usuario obtenerUsuarioAutenticado() {
        String email = SecurityContextHolder.getContext()
                .getAuthentication()
                .getName();

        return usuarioRepository.findByEmailIgnoreCase(email)
                .orElseThrow();
    }

    private LoginResponse crearLoginResponse(Usuario usuario) {
        List<SimpleGrantedAuthority> authorities = usuario.getRoles()
                .stream()
                .map(rol -> new SimpleGrantedAuthority("ROLE_" + rol.name()))
                .toList();

        Authentication authentication = new UsernamePasswordAuthenticationToken(
                usuario.getEmail(),
                null,
                authorities
        );

        return crearLoginResponse(usuario, authentication);
    }

    private LoginResponse crearLoginResponse(Usuario usuario, Authentication authentication) {
        String token = jwtService.generarToken(authentication);

        return new LoginResponse(
                token,
                usuario.getNombre(),
                usuario.getEmail(),
                obtenerRolPrincipal(usuario),
                usuario.getRoles()
        );
    }

    private String obtenerRolPrincipal(Usuario usuario) {
        return usuario.getRoles()
                .stream()
                .findFirst()
                .map(Enum::name)
                .orElse(RolUsuario.PRENSA.name());
    }

    private String normalizarEmail(String email) {
        return email.trim().toLowerCase();
    }
}
