package ar.gov.ensenada.backend.contenido;

import com.fasterxml.jackson.core.JsonProcessingException;
import com.fasterxml.jackson.databind.JsonNode;
import com.fasterxml.jackson.databind.ObjectMapper;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.web.server.ResponseStatusException;

import java.time.LocalDateTime;

@Service
public class PortalSiteContentService {

    private static final long SINGLETON_ID = 1L;
    private static final int MAX_JSON_LENGTH = 60_000;

    private final PortalSiteContentRepository repository;
    private final ObjectMapper objectMapper;

    public PortalSiteContentService(PortalSiteContentRepository repository, ObjectMapper objectMapper) {
        this.repository = repository;
        this.objectMapper = objectMapper;
    }

    public JsonNode obtener() {
        PortalSiteContent contenido = repository.findById(SINGLETON_ID)
                .orElseGet(() -> repository.save(new PortalSiteContent(SINGLETON_ID, "{}", LocalDateTime.now())));
        return parsear(contenido.getContenidoJson());
    }

    public JsonNode guardar(JsonNode nuevoContenido) {
        if (nuevoContenido == null || !nuevoContenido.isObject()) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El contenido del portal debe ser un objeto JSON");
        }

        String serializado;
        try {
            serializado = objectMapper.writeValueAsString(nuevoContenido);
        } catch (JsonProcessingException e) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El contenido del portal no es válido");
        }

        if (serializado.length() > MAX_JSON_LENGTH) {
            throw new ResponseStatusException(HttpStatus.PAYLOAD_TOO_LARGE, "El contenido del portal supera el tamaño permitido");
        }

        PortalSiteContent contenido = repository.findById(SINGLETON_ID)
                .orElseGet(() -> new PortalSiteContent(SINGLETON_ID, "{}", LocalDateTime.now()));
        contenido.actualizar(serializado);
        repository.save(contenido);
        return parsear(serializado);
    }

    private JsonNode parsear(String json) {
        try {
            return objectMapper.readTree(json);
        } catch (JsonProcessingException e) {
            throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR, "El contenido guardado del portal es inválido");
        }
    }
}
