package ar.gov.ensenada.backend.contenido;

import jakarta.persistence.Column;
import jakarta.persistence.Entity;
import jakarta.persistence.Id;
import jakarta.persistence.Table;

import java.time.LocalDateTime;

@Entity
@Table(name = "portal_site_content")
public class PortalSiteContent {

    @Id
    private Long id;

    @Column(name = "contenido_json", nullable = false, columnDefinition = "TEXT")
    private String contenidoJson;

    @Column(name = "updated_at", nullable = false)
    private LocalDateTime updatedAt;

    protected PortalSiteContent() {
    }

    public PortalSiteContent(Long id, String contenidoJson, LocalDateTime updatedAt) {
        this.id = id;
        this.contenidoJson = contenidoJson;
        this.updatedAt = updatedAt;
    }

    public Long getId() {
        return id;
    }

    public String getContenidoJson() {
        return contenidoJson;
    }

    public LocalDateTime getUpdatedAt() {
        return updatedAt;
    }

    public void actualizar(String contenidoJson) {
        this.contenidoJson = contenidoJson;
        this.updatedAt = LocalDateTime.now();
    }
}
