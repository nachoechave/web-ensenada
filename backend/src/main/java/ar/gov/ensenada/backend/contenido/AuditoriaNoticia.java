package ar.gov.ensenada.backend.contenido;

import jakarta.persistence.*;
import java.time.Instant;

@Entity
@Table(name = "auditoria_noticias")
public class AuditoriaNoticia {

    @Id
    @GeneratedValue(strategy = GenerationType.IDENTITY)
    private Long id;

    @Column(name = "noticia_id", nullable = false)
    private Long noticiaId;

    @Column(name = "noticia_titulo", nullable = false)
    private String noticiaTitulo;

    @Column(nullable = false, length = 40)
    private String accion;

    @Column(name = "estado_resultante", nullable = false, length = 30)
    private String estadoResultante;

    @Column(name = "usuario_nombre", nullable = false)
    private String usuarioNombre;

    @Column(name = "usuario_email", nullable = false)
    private String usuarioEmail;

    @Column(nullable = false)
    private Instant fecha;

    public Long getId() { return id; }
    public Long getNoticiaId() { return noticiaId; }
    public String getNoticiaTitulo() { return noticiaTitulo; }
    public String getAccion() { return accion; }
    public String getEstadoResultante() { return estadoResultante; }
    public String getUsuarioNombre() { return usuarioNombre; }
    public String getUsuarioEmail() { return usuarioEmail; }
    public Instant getFecha() { return fecha; }

    public void setNoticiaId(Long noticiaId) { this.noticiaId = noticiaId; }
    public void setNoticiaTitulo(String noticiaTitulo) { this.noticiaTitulo = noticiaTitulo; }
    public void setAccion(String accion) { this.accion = accion; }
    public void setEstadoResultante(String estadoResultante) { this.estadoResultante = estadoResultante; }
    public void setUsuarioNombre(String usuarioNombre) { this.usuarioNombre = usuarioNombre; }
    public void setUsuarioEmail(String usuarioEmail) { this.usuarioEmail = usuarioEmail; }
    public void setFecha(Instant fecha) { this.fecha = fecha; }
}
