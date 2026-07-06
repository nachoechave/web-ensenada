package ar.gov.ensenada.backend.contenido;

import jakarta.persistence.Column;
import jakarta.persistence.Entity;
import jakarta.persistence.Id;
import jakarta.persistence.Table;

@Entity
@Table(name = "contenido_sitio")
public class ContenidoSitio {

    @Id
    private Long id = 1L;

    @Column(nullable = false)
    private String heroTitulo;

    @Column(nullable = false, length = 700)
    private String heroBajada;

    @Column(nullable = false, length = 700)
    private String footerDescripcion;

    @Column(nullable = false)
    private String emailContacto;

    @Column(nullable = true)
    private String telefonoContacto;

    @Column(nullable = true)
    private String direccionMunicipio;

    @Column(nullable = true)
    private String horarioAtencion;

    @Column(nullable = true, length = 700)
    private String textoTramites;

    @Column(nullable = true, length = 700)
    private String notaInstitucionalContacto;

    public Long getId() {
        return id;
    }

    public String getHeroTitulo() {
        return heroTitulo;
    }

    public String getHeroBajada() {
        return heroBajada;
    }

    public String getFooterDescripcion() {
        return footerDescripcion;
    }

    public String getEmailContacto() {
        return emailContacto;
    }

    public String getTelefonoContacto() {
        return telefonoContacto;
    }

    public String getDireccionMunicipio() {
        return direccionMunicipio;
    }

    public String getHorarioAtencion() {
        return horarioAtencion;
    }

    public String getTextoTramites() {
        return textoTramites;
    }

    public String getNotaInstitucionalContacto() {
        return notaInstitucionalContacto;
    }

    public void setId(Long id) {
        this.id = id;
    }

    public void setHeroTitulo(String heroTitulo) {
        this.heroTitulo = heroTitulo;
    }

    public void setHeroBajada(String heroBajada) {
        this.heroBajada = heroBajada;
    }

    public void setFooterDescripcion(String footerDescripcion) {
        this.footerDescripcion = footerDescripcion;
    }

    public void setEmailContacto(String emailContacto) {
        this.emailContacto = emailContacto;
    }

    public void setTelefonoContacto(String telefonoContacto) {
        this.telefonoContacto = telefonoContacto;
    }

    public void setDireccionMunicipio(String direccionMunicipio) {
        this.direccionMunicipio = direccionMunicipio;
    }

    public void setHorarioAtencion(String horarioAtencion) {
        this.horarioAtencion = horarioAtencion;
    }

    public void setTextoTramites(String textoTramites) {
        this.textoTramites = textoTramites;
    }

    public void setNotaInstitucionalContacto(String notaInstitucionalContacto) {
        this.notaInstitucionalContacto = notaInstitucionalContacto;
    }
}
