package ar.gov.ensenada.backend.contenido;

import ar.gov.ensenada.backend.archivos.ImagenSegura;
import ar.gov.ensenada.backend.archivos.ImagenSitioOptimizada;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.web.bind.annotation.PostMapping;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RequestParam;
import org.springframework.web.bind.annotation.RestController;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardOpenOption;
import java.util.UUID;

@RestController
@RequestMapping("/api/admin/archivos")
public class ArchivoUploadController {

    @Value("${app.uploads.dir:uploads}")
    private String uploadsDir;

    @PostMapping(value = "/noticias", consumes = MediaType.MULTIPART_FORM_DATA_VALUE)
    public ArchivoUploadResponse subirNoticia(@RequestParam("archivo") MultipartFile archivo) {
        var image = ImagenSegura.procesar(archivo);
        return guardarImagen(image.bytes(), image.extension(), image.mime(), "noticias");
    }

    @PostMapping(value = "/sitio", consumes = MediaType.MULTIPART_FORM_DATA_VALUE)
    public ArchivoUploadResponse subirSitio(@RequestParam("archivo") MultipartFile archivo) {
        var image = ImagenSitioOptimizada.procesar(archivo);
        return guardarImagen(image.bytes(), image.extension(), image.mime(), "sitio");
    }

    private ArchivoUploadResponse guardarImagen(byte[] bytes, String extension, String mime, String carpeta) {
        String filename = UUID.randomUUID() + "." + extension;
        Path directory = Path.of(uploadsDir, carpeta).toAbsolutePath().normalize();
        try {
            Files.createDirectories(directory);
            Files.write(directory.resolve(filename), bytes, StandardOpenOption.CREATE_NEW);
            return new ArchivoUploadResponse(
                    "/uploads/" + carpeta + "/" + filename,
                    filename,
                    mime,
                    bytes.length
            );
        } catch (IOException e) {
            throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR, "No se pudo guardar la imagen");
        }
    }
}
