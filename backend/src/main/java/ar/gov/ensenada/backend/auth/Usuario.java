package ar.gov.ensenada.backend.auth;

import jakarta.persistence.*;

import java.util.HashSet;
import java.util.Set;

@Entity
@Table(name = "usuarios")
public class Usuario {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String nombre;

    @Column(nullable = false, unique = true)
    private String email;

    @Column(nullable = false)
    private String password;

    @Column(nullable = false)
    private String rol = RolUsuario.PRENSA.name();

    @Enumerated(EnumType.STRING)
    @ElementCollection(fetch = FetchType.EAGER)
    @CollectionTable(name = "usuario_roles", joinColumns = @JoinColumn(name = "usuario_id"))
    @Column(name = "rol", nullable = false)
    private Set<RolUsuario> roles = new HashSet<>();

    @Column(nullable = false)
    private boolean activo = true;

    public Usuario() {
    }

    public Usuario(String nombre, String email, String password, Set<RolUsuario> roles, boolean activo) {
        this.nombre = nombre;
        this.email = email;
        this.password = password;
        this.roles = roles;
        this.rol = obtenerRolPrincipal(roles);
        this.activo = activo;
    }

    public Long getId() {
        return id;
    }

    public String getNombre() {
        return nombre;
    }

    public String getEmail() {
        return email;
    }

    public String getPassword() {
        return password;
    }

    public Set<RolUsuario> getRoles() {
        if ((roles == null || roles.isEmpty()) && rol != null) {
            return Set.of(normalizarRol(rol));
        }

        return roles;
    }

    public String getRol() {
        return rol;
    }

    public boolean isActivo() {
        return activo;
    }

    public void setId(Long id) {
        this.id = id;
    }

    public void setNombre(String nombre) {
        this.nombre = nombre;
    }

    public void setEmail(String email) {
        this.email = email;
    }

    public void setPassword(String password) {
        this.password = password;
    }

    public void setRoles(Set<RolUsuario> roles) {
        this.roles = roles;
        this.rol = obtenerRolPrincipal(roles);
    }

    public void setRol(String rol) {
        this.rol = rol;
    }

    public void setActivo(boolean activo) {
        this.activo = activo;
    }

    private String obtenerRolPrincipal(Set<RolUsuario> roles) {
        if (roles == null || roles.isEmpty()) {
            return RolUsuario.PRENSA.name();
        }

        return roles.stream().findFirst().orElse(RolUsuario.PRENSA).name();
    }

    private RolUsuario normalizarRol(String rol) {
        if ("ADMIN".equals(rol) || "ROLE_ADMIN".equals(rol)) {
            return RolUsuario.SUPER_ADMIN;
        }

        return RolUsuario.valueOf(rol.replace("ROLE_", ""));
    }
}
