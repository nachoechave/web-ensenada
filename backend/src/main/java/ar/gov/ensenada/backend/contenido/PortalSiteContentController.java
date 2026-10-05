package ar.gov.ensenada.backend.contenido;

import tools.jackson.databind.JsonNode;
import org.springframework.http.CacheControl;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class PortalSiteContentController {

    private final PortalSiteContentService service;

    public PortalSiteContentController(PortalSiteContentService service) {
        this.service = service;
    }

    @GetMapping(value = "/api/site-content", produces = MediaType.APPLICATION_JSON_VALUE)
    public ResponseEntity<String> obtenerPublico() {
        return ResponseEntity.ok()
                .cacheControl(CacheControl.noStore())
                .contentType(MediaType.APPLICATION_JSON)
                .body(service.obtener());
    }

    @GetMapping(value = "/api/admin/site-content", produces = MediaType.APPLICATION_JSON_VALUE)
    public ResponseEntity<String> obtenerAdmin() {
        return ResponseEntity.ok()
                .cacheControl(CacheControl.noStore())
                .contentType(MediaType.APPLICATION_JSON)
                .body(service.obtener());
    }

    @PutMapping(
            value = "/api/admin/site-content",
            consumes = MediaType.APPLICATION_JSON_VALUE,
            produces = MediaType.APPLICATION_JSON_VALUE
    )
    public ResponseEntity<JsonNode> guardar(@RequestBody JsonNode contenido) {
        return ResponseEntity.ok()
                .cacheControl(CacheControl.noStore())
                .contentType(MediaType.APPLICATION_JSON)
                .body(service.guardar(contenido));
    }
}
