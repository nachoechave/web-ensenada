package ar.gov.ensenada.backend.contenido;

import jakarta.persistence.*;

@Entity
@Table(name = "documentos_municipales")
public class DocumentoMunicipal {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable = false)
    private String titulo;

    @Column(nullable = false)
    private String fecha;

    @Column(nullable = false)
    private String hora;

    @Column(nullable = false)
    private Integer anio;

    @Enumerated(EnumType.STRING)
    @Column(nullable = false)
    private TipoDocumentoMunicipal tipo;

    @Column(length = 700)
    private String descripcion;

    @Column(nullable = false, length = 1000)
    private String archivoUrl;

    public Long getId() {
        return id;
    }

    public String getTitulo() {
        return titulo;
    }

    public String getFecha() {
        return fecha;
    }

    public String getHora() {
        return hora;
    }

    public Integer getAnio() {
        return anio;
    }

    public TipoDocumentoMunicipal getTipo() {
        return tipo;
    }

    public String getDescripcion() {
        return descripcion;
    }

    public String getArchivoUrl() {
        return archivoUrl;
    }

    public void setId(Long id) {
        this.id = id;
    }

    public void setTitulo(String titulo) {
        this.titulo = titulo;
    }

    public void setFecha(String fecha) {
        this.fecha = fecha;
    }

    public void setHora(String hora) {
        this.hora = hora;
    }

    public void setAnio(Integer anio) {
        this.anio = anio;
    }

    public void setTipo(TipoDocumentoMunicipal tipo) {
        this.tipo = tipo;
    }

    public void setDescripcion(String descripcion) {
        this.descripcion = descripcion;
    }

    public void setArchivoUrl(String archivoUrl) {
        this.archivoUrl = archivoUrl;
    }
}
