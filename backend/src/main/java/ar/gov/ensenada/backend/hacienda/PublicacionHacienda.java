package ar.gov.ensenada.backend.hacienda;

import ar.gov.ensenada.backend.contenido.EstadoPublicacion;
import jakarta.persistence.*;
import org.hibernate.annotations.JdbcTypeCode;
import java.sql.Types;
import java.time.LocalDate;
import java.util.ArrayList;
import java.util.List;

@Entity
@Table(name="publicaciones_hacienda")
public class PublicacionHacienda {
    @Id @GeneratedValue(strategy=GenerationType.IDENTITY) private Long id;
    @Column(nullable=false) private String titulo;
    @Column(nullable=false, columnDefinition="TEXT") private String descripcion;
    @Enumerated(EnumType.STRING) @JdbcTypeCode(Types.VARCHAR)
    @Column(nullable=false, length=60) private TipoPublicacionHacienda tipo;
    @Column(nullable=false) private LocalDate fechaPublicacion;
    @Enumerated(EnumType.STRING) @JdbcTypeCode(Types.VARCHAR)
    @Column(nullable=false, length=20) private EstadoPublicacion estado;
    @OneToMany(mappedBy="publicacion", cascade=CascadeType.ALL, orphanRemoval=true)
    @OrderBy("orden ASC, id ASC") private List<ArchivoHacienda> archivos = new ArrayList<>();

    public Long getId(){return id;}
    public String getTitulo(){return titulo;}
    public String getDescripcion(){return descripcion;}
    public TipoPublicacionHacienda getTipo(){return tipo;}
    public LocalDate getFechaPublicacion(){return fechaPublicacion;}
    public EstadoPublicacion getEstado(){return estado;}
    public List<ArchivoHacienda> getArchivos(){return archivos;}
    public void setEstado(EstadoPublicacion estado){this.estado=estado;}
    public void actualizar(PublicacionHaciendaRequest request){
        titulo=request.titulo().trim(); descripcion=request.descripcion().trim(); tipo=request.tipo();
        fechaPublicacion=request.fechaPublicacion(); estado=request.estado();
    }
}
