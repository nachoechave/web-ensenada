package ar.gov.ensenada.backend.contenido;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;
import org.springframework.web.servlet.support.ServletUriComponentsBuilder;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardCopyOption;
import java.text.Normalizer;
import java.util.Locale;
import java.util.Set;
import java.util.UUID;

@RestController
@RequestMapping("/api/admin/archivos")
public class ArchivoUploadController {

    private static final Set<String> SECCIONES_PERMITIDAS = Set.of(
            "noticias",
            "boletin-oficial",
            "hacienda"
    );

    @Value("${app.uploads.dir:uploads}")
    private String uploadsDir;

    @PostMapping(value = "/{seccion}", consumes = MediaType.MULTIPART_FORM_DATA_VALUE)
    public ArchivoUploadResponse subir(
            @PathVariable String seccion,
            @RequestParam("archivo") MultipartFile archivo
    ) {
        validarSeccion(seccion);
        validarArchivo(seccion, archivo);

        String nombreArchivo = generarNombreArchivo(archivo.getOriginalFilename());
        Path carpetaDestino = Path.of(uploadsDir, seccion).toAbsolutePath().normalize();
        Path destino = carpetaDestino.resolve(nombreArchivo).normalize();

        if (!destino.startsWith(carpetaDestino)) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Nombre de archivo inválido");
        }

        try {
            Files.createDirectories(carpetaDestino);
            Files.copy(archivo.getInputStream(), destino, StandardCopyOption.REPLACE_EXISTING);
        } catch (IOException e) {
            throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR, "No se pudo guardar el archivo");
        }

        String url = ServletUriComponentsBuilder.fromCurrentContextPath()
                .path("/uploads/")
                .path(seccion)
                .path("/")
                .path(nombreArchivo)
                .toUriString();

        return new ArchivoUploadResponse(
                url,
                archivo.getOriginalFilename(),
                archivo.getContentType(),
                archivo.getSize()
        );
    }

    private void validarSeccion(String seccion) {
        if (!SECCIONES_PERMITIDAS.contains(seccion)) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Sección inválida");
        }
    }

    private void validarArchivo(String seccion, MultipartFile archivo) {
        if (archivo.isEmpty()) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El archivo está vacío");
        }

        String contentType = archivo.getContentType() == null ? "" : archivo.getContentType();

        if ("noticias".equals(seccion) && !contentType.startsWith("image/")) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Las noticias solo aceptan imágenes");
        }

        boolean esDocumentoValido = contentType.equals("application/pdf") || contentType.startsWith("image/");

        if (!"noticias".equals(seccion) && !esDocumentoValido) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Solo se aceptan PDF o imágenes escaneadas");
        }
    }

    private String generarNombreArchivo(String nombreOriginal) {
        String nombre = nombreOriginal == null || nombreOriginal.isBlank()
                ? "archivo"
                : nombreOriginal;

        String nombreNormalizado = Normalizer.normalize(nombre, Normalizer.Form.NFD)
                .replaceAll("\\p{M}", "")
                .replaceAll("[^a-zA-Z0-9._-]", "-")
                .toLowerCase(Locale.ROOT);

        int punto = nombreNormalizado.lastIndexOf('.');
        String extension = punto >= 0 ? nombreNormalizado.substring(punto) : "";
        String base = punto >= 0 ? nombreNormalizado.substring(0, punto) : nombreNormalizado;

        if (base.length() > 50) {
            base = base.substring(0, 50);
        }

        return base + "-" + UUID.randomUUID() + extension;
    }
}
