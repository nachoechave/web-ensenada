package ar.gov.ensenada.backend.hacienda;

import jakarta.persistence.*;

@Entity
@Table(name="archivos_hacienda")
public class ArchivoHacienda {
    @Id @GeneratedValue(strategy=GenerationType.IDENTITY) private Long id;
    @ManyToOne(fetch=FetchType.LAZY, optional=false)
    @JoinColumn(name="publicacion_hacienda_id", nullable=false) private PublicacionHacienda publicacion;
    @Column(nullable=false) private String nombreOriginal;
    @Column(nullable=false, unique=true, length=50) private String nombreAlmacenado;
    @Column(nullable=false, length=30) private String tipoMime;
    @Column(nullable=false) private int orden;
    @Column(nullable=false) private long tamanio;
    protected ArchivoHacienda() {}
    public ArchivoHacienda(PublicacionHacienda publicacion, String nombreOriginal, String nombreAlmacenado, String tipoMime, int orden, long tamanio){
        this.publicacion=publicacion;this.nombreOriginal=nombreOriginal;this.nombreAlmacenado=nombreAlmacenado;
        this.tipoMime=tipoMime;this.orden=orden;this.tamanio=tamanio;
    }
    public Long getId(){return id;}
    public PublicacionHacienda getPublicacion(){return publicacion;}
    public String getNombreOriginal(){return nombreOriginal;}
    public String getNombreAlmacenado(){return nombreAlmacenado;}
    public String getTipoMime(){return tipoMime;}
    public int getOrden(){return orden;}
    public long getTamanio(){return tamanio;}
    public void setOrden(int orden){this.orden=orden;}
}
