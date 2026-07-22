package ar.gov.ensenada.backend.contenido;

import jakarta.persistence.*;

@Entity
@Table(name = "areas_municipales")
public class AreaMunicipal {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String nombre;

    @Column(nullable = false, length = 700)
    private String descripcion;

    @Column(nullable = false)
    private String telefono;

    @Column(nullable = true)
    private String email;

    @Column
    private String direccion;

    @Column(nullable = false)
    private String horario;

    public Long getId() {
        return id;
    }

    public String getNombre() {
        return nombre;
    }

    public String getDescripcion() {
        return descripcion;
    }

    public String getTelefono() {
        return telefono;
    }

    public String getEmail() {
        return email;
    }

    public String getDireccion() {
        return direccion;
    }

    public String getHorario() {
        return horario;
    }

    public void setId(Long id) {
        this.id = id;
    }

    public void setNombre(String nombre) {
        this.nombre = nombre;
    }

    public void setDescripcion(String descripcion) {
        this.descripcion = descripcion;
    }

    public void setTelefono(String telefono) {
        this.telefono = telefono;
    }

    public void setEmail(String email) {
        this.email = email;
    }

    public void setDireccion(String direccion) {
        this.direccion = direccion;
    }

    public void setHorario(String horario) {
        this.horario = horario;
    }
}
