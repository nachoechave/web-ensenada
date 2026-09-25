package ar.gov.ensenada.backend.contenido;

import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.web.server.ResponseStatusException;

import java.time.LocalDateTime;

@Service
public class PortalSiteContentService {

    private static final long SINGLETON_ID = 1L;
    private static final int MAX_JSON_LENGTH = 60_000;

    private final PortalSiteContentRepository repository;

    public PortalSiteContentService(PortalSiteContentRepository repository) {
        this.repository = repository;
    }

    public String obtener() {
        return repository.findById(SINGLETON_ID)
                .orElseGet(() -> repository.save(new PortalSiteContent(SINGLETON_ID, "{}", LocalDateTime.now())))
                .getContenidoJson();
    }

    public String guardar(String nuevoContenido) {
        String normalizado = nuevoContenido == null ? "" : nuevoContenido.trim();
        if (!normalizado.startsWith("{") || !normalizado.endsWith("}")) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El contenido del portal debe ser un objeto JSON");
        }
        if (normalizado.length() > MAX_JSON_LENGTH) {
            throw new ResponseStatusException(HttpStatus.PAYLOAD_TOO_LARGE, "El contenido del portal supera el tamaño permitido");
        }

        PortalSiteContent contenido = repository.findById(SINGLETON_ID)
                .orElseGet(() -> new PortalSiteContent(SINGLETON_ID, "{}", LocalDateTime.now()));
        contenido.actualizar(normalizado);
        repository.save(contenido);
        return normalizado;
    }
}
