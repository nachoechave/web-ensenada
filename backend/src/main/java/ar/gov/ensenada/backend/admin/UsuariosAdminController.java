package ar.gov.ensenada.backend.admin;

import ar.gov.ensenada.backend.auth.Usuario;
import ar.gov.ensenada.backend.auth.UsuarioRepository;
import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;

@RestController
@RequestMapping("/api/admin/usuarios")
public class UsuariosAdminController {

    private final UsuarioRepository usuarioRepository;
    private final PasswordEncoder passwordEncoder;

    public UsuariosAdminController(
            UsuarioRepository usuarioRepository,
            PasswordEncoder passwordEncoder
    ) {
        this.usuarioRepository = usuarioRepository;
        this.passwordEncoder = passwordEncoder;
    }

    @GetMapping
    public List<UsuarioAdminResponse> listar() {
        return usuarioRepository.findAll()
                .stream()
                .map(this::toResponse)
                .toList();
    }

    @PostMapping
    @ResponseStatus(HttpStatus.CREATED)
    public UsuarioAdminResponse crear(@Valid @RequestBody UsuarioCrearRequest request) {
        validarRolesPrensa(request.roles());
        String email = request.email().trim().toLowerCase();

        if (usuarioRepository.findByEmailIgnoreCase(email).isPresent()) {
            throw new ResponseStatusException(HttpStatus.CONFLICT, "Ya existe un usuario con ese email");
        }

        Usuario usuario = new Usuario(
                request.nombre(),
                email,
                passwordEncoder.encode(request.password()),
                request.roles(),
                request.activo()
        );

        return toResponse(usuarioRepository.save(usuario));
    }

    @PutMapping("/{id}/roles")
    public UsuarioAdminResponse actualizarRoles(
            @PathVariable Long id,
            @Valid @RequestBody UsuarioRolesRequest request
    ) {
        Usuario usuario = usuarioRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Usuario no encontrado"));

        protegerAdministrador(usuario);
        validarRolesPrensa(request.roles());
        usuario.setRoles(request.roles());

        return toResponse(usuarioRepository.save(usuario));
    }

    @PutMapping("/{id}/activo")
    public UsuarioAdminResponse activar(@PathVariable Long id, @Valid @RequestBody UsuarioActivoRequest request) {
        Usuario usuario = usuarioRepository.findById(id)
            .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Usuario no encontrado"));
        protegerAdministrador(usuario);
        usuario.setActivo(request.activo());
        return toResponse(usuarioRepository.save(usuario));
    }

    private void protegerAdministrador(Usuario usuario) {
        if (usuario.getRoles().contains(ar.gov.ensenada.backend.auth.RolUsuario.SUPER_ADMIN))
            throw new ResponseStatusException(HttpStatus.FORBIDDEN, "Los superadministradores no se modifican desde este panel");
    }
    private void validarRolesPrensa(java.util.Set<ar.gov.ensenada.backend.auth.RolUsuario> roles) {
        if (!java.util.Set.of(ar.gov.ensenada.backend.auth.RolUsuario.PRENSA).equals(roles))
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Solo se pueden gestionar usuarios PRENSA");
    }
    private UsuarioAdminResponse toResponse(Usuario usuario) {
        return new UsuarioAdminResponse(
                usuario.getId(),
                usuario.getNombre(),
                usuario.getEmail(),
                usuario.getRoles(),
                usuario.isActivo()
        );
    }
}
