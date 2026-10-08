package ar.gov.ensenada.backend.contenido;

import ar.gov.ensenada.backend.archivos.ArchivoStorageService;
import org.springframework.http.CacheControl;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PathVariable;
import org.springframework.web.bind.annotation.RestController;

import java.util.concurrent.TimeUnit;

@RestController
public class ImagenPublicaController {
    private final ArchivoStorageService archivos;

    public ImagenPublicaController(ArchivoStorageService archivos) {
        this.archivos = archivos;
    }

    @GetMapping("/uploads/{categoria:noticias|sitio}/{nombre:[a-zA-Z0-9._-]+}")
    public ResponseEntity<byte[]> imagen(@PathVariable String categoria, @PathVariable String nombre) {
        byte[] contenido = archivos.leer(categoria, nombre);
        MediaType tipo = nombre.toLowerCase().endsWith(".png") ? MediaType.IMAGE_PNG : MediaType.IMAGE_JPEG;
        return ResponseEntity.ok().contentType(tipo)
                .cacheControl(CacheControl.maxAge(1, TimeUnit.DAYS).cachePublic())
                .body(contenido);
    }
}
