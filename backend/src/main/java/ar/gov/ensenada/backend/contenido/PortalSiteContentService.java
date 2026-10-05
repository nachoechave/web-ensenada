package ar.gov.ensenada.backend.contenido;

import tools.jackson.core.JsonProcessingException;
import tools.jackson.databind.JsonNode;
import tools.jackson.databind.ObjectMapper;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.web.server.ResponseStatusException;

import java.time.LocalDateTime;

@Service
public class PortalSiteContentService {

    private static final long SINGLETON_ID = 1L;
    private static final int MAX_JSON_LENGTH = 160_000;

    private final PortalSiteContentRepository repository;
    private final ObjectMapper objectMapper;

    public PortalSiteContentService(PortalSiteContentRepository repository, ObjectMapper objectMapper) {
        this.repository = repository;
        this.objectMapper = objectMapper;
    }

    public String obtener() {
        return repository.findById(SINGLETON_ID)
                .orElseGet(() -> repository.save(new PortalSiteContent(SINGLETON_ID, "{}", LocalDateTime.now())))
                .getContenidoJson();
    }

    public JsonNode guardar(JsonNode nuevoContenido) {
        if (nuevoContenido == null || !nuevoContenido.isObject()) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El contenido del portal debe ser un objeto JSON");
        }

        final String normalizado;
        try {
            normalizado = objectMapper.writeValueAsString(nuevoContenido);
        } catch (JsonProcessingException e) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "No se pudo serializar el contenido del portal");
        }

        if (normalizado.length() > MAX_JSON_LENGTH) {
            throw new ResponseStatusException(HttpStatus.PAYLOAD_TOO_LARGE, "El contenido del portal supera el tamaño permitido");
        }

        PortalSiteContent contenido = repository.findById(SINGLETON_ID)
                .orElseGet(() -> new PortalSiteContent(SINGLETON_ID, "{}", LocalDateTime.now()));
        contenido.actualizar(normalizado);
        repository.saveAndFlush(contenido);

        try {
            return objectMapper.readTree(contenido.getContenidoJson());
        } catch (JsonProcessingException e) {
            throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR, "No se pudo verificar el contenido guardado");
        }
    }
}
