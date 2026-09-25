package ar.gov.ensenada.backend.contenido;

import com.fasterxml.jackson.databind.JsonNode;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
public class PortalSiteContentController {

    private final PortalSiteContentService service;

    public PortalSiteContentController(PortalSiteContentService service) {
        this.service = service;
    }

    @GetMapping("/api/site-content")
    public JsonNode obtenerPublico() {
        return service.obtener();
    }

    @GetMapping("/api/admin/site-content")
    public JsonNode obtenerAdmin() {
        return service.obtener();
    }

    @PutMapping("/api/admin/site-content")
    public JsonNode guardar(@RequestBody JsonNode contenido) {
        return service.guardar(contenido);
    }
}
