package ar.gov.ensenada.backend.contenido;

import jakarta.persistence.*;

@Entity
@Table(name = "noticias")
public class Noticia {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String titulo;

    @Column(nullable = false, length = 500)
    private String bajada;

    @Column(nullable = false, columnDefinition = "TEXT")
    private String contenido;

    @Column(nullable = false, length = 1000)
    private String imagen;

    @Column(nullable = false)
    private String categoria;

    @Column(nullable = false)
    private java.time.LocalDate fechaPublicacion;

    @Enumerated(EnumType.STRING)
    @org.hibernate.annotations.JdbcTypeCode(java.sql.Types.VARCHAR)
    @Column(nullable = false)
    private EstadoPublicacion estado = EstadoPublicacion.BORRADOR;

    @Column(nullable = false)
    private boolean destacada = false;

    public Long getId() {
        return id;
    }

    public String getTitulo() {
        return titulo;
    }

    public String getBajada() {
        return bajada;
    }

    public String getContenido() {
        return contenido;
    }

    public String getImagen() {
        return imagen;
    }

    public String getCategoria() {
        return categoria;
    }

    public java.time.LocalDate getFechaPublicacion() {
        return fechaPublicacion;
    }

    public EstadoPublicacion getEstado() {
        return estado;
    }

    public boolean isDestacada() {
        return destacada;
    }

    public void setId(Long id) {
        this.id = id;
    }

    public void setTitulo(String titulo) {
        this.titulo = titulo;
    }

    public void setBajada(String bajada) {
        this.bajada = bajada;
    }

    public void setContenido(String contenido) {
        this.contenido = contenido;
    }

    public void setImagen(String imagen) {
        this.imagen = imagen;
    }

    public void setCategoria(String categoria) {
        this.categoria = categoria;
    }

    public void setFechaPublicacion(java.time.LocalDate fechaPublicacion) {
        this.fechaPublicacion = fechaPublicacion;
    }

    public void setEstado(EstadoPublicacion estado) {
        this.estado = estado;
    }

    public void setDestacada(boolean destacada) {
        this.destacada = destacada;
    }
}
