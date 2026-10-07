package ar.gov.ensenada.backend.proveedores;

import jakarta.persistence.*;
import java.time.LocalDateTime;

@Entity
@Table(name = "documentos_proveedores")
public class DocumentoProveedor {
    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(nullable=false, length=180) private String titulo;
    @Column(nullable=false, length=500) private String descripcion;
    @Column(name="nombre_original", nullable=false) private String nombreOriginal;
    @Column(name="nombre_almacenado", nullable=false, unique=true) private String nombreAlmacenado;
    @Column(name="tipo_mime", nullable=false, length=120) private String tipoMime;
    @Column(nullable=false) private long tamanio;
    @Column(nullable=false) private int orden;
    @Column(nullable=false) private boolean activo;
    @Column(name="created_at", nullable=false) private LocalDateTime createdAt;
    @Column(name="updated_at", nullable=false) private LocalDateTime updatedAt;

    protected DocumentoProveedor() {}

    public DocumentoProveedor(String titulo, String descripcion, String nombreOriginal, String nombreAlmacenado,
                              String tipoMime, long tamanio, int orden) {
        this.titulo = titulo;
        this.descripcion = descripcion;
        this.nombreOriginal = nombreOriginal;
        this.nombreAlmacenado = nombreAlmacenado;
        this.tipoMime = tipoMime;
        this.tamanio = tamanio;
        this.orden = orden;
        this.activo = true;
        this.createdAt = LocalDateTime.now();
        this.updatedAt = this.createdAt;
    }

    public Long getId(){ return id; }
    public String getTitulo(){ return titulo; }
    public String getDescripcion(){ return descripcion; }
    public String getNombreOriginal(){ return nombreOriginal; }
    public String getNombreAlmacenado(){ return nombreAlmacenado; }
    public String getTipoMime(){ return tipoMime; }
    public long getTamanio(){ return tamanio; }
    public int getOrden(){ return orden; }
    public boolean isActivo(){ return activo; }

    public void editar(String titulo, String descripcion, boolean activo) {
        this.titulo = titulo;
        this.descripcion = descripcion;
        this.activo = activo;
        this.updatedAt = LocalDateTime.now();
    }

    public void setOrden(int orden) {
        this.orden = orden;
        this.updatedAt = LocalDateTime.now();
    }
}
