package ar.gov.ensenada.backend.contenido;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;
import javax.imageio.ImageIO;
import javax.imageio.ImageReader;
import javax.imageio.stream.ImageInputStream;
import java.awt.image.BufferedImage;
import java.io.*;
import java.nio.file.*;
import java.util.*;

@RestController
@RequestMapping("/api/admin/archivos")
public class ArchivoUploadController {
    private static final long MAX_BYTES = 5 * 1024 * 1024;
    private static final long MAX_PIXELS = 20_000_000;
    @Value("${app.uploads.dir:uploads}") private String uploadsDir;

    @PostMapping(value="/noticias", consumes=MediaType.MULTIPART_FORM_DATA_VALUE)
    public ArchivoUploadResponse subir(@RequestParam("archivo") MultipartFile archivo) {
        if (archivo.isEmpty() || archivo.getSize() > MAX_BYTES) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "La imagen debe tener entre 1 byte y 5 MB");
        }
        // Decode and re-encode: filenames, MIME and embedded metadata are untrusted.
        BufferedImage image;
        String format;
        try (InputStream input = archivo.getInputStream(); ImageInputStream stream = ImageIO.createImageInputStream(input)) {
            Iterator<ImageReader> readers = ImageIO.getImageReaders(stream);
            if (!readers.hasNext()) throw invalida();
            ImageReader reader = readers.next();
            try {
                format = reader.getFormatName().toLowerCase(Locale.ROOT);
                if (!Set.of("jpeg", "png").contains(format)) throw invalida();
                reader.setInput(stream, true, true);
                int width = reader.getWidth(0), height = reader.getHeight(0);
                if (width < 1 || height < 1 || (long) width * height > MAX_PIXELS) throw invalida();
                image = reader.read(0);
                if (image == null) throw invalida();
            } finally { reader.dispose(); }
        } catch (IOException | IllegalArgumentException e) { throw invalida(); }
        String filename = UUID.randomUUID() + (format.equals("jpeg") ? ".jpg" : ".png");
        Path directory = Path.of(uploadsDir, "noticias").toAbsolutePath().normalize();
        try {
            ByteArrayOutputStream encoded = new ByteArrayOutputStream();
            if (!ImageIO.write(image, format, encoded)) throw invalida();
            if (encoded.size() > MAX_BYTES) throw invalida();
            Files.createDirectories(directory);
            Files.write(directory.resolve(filename), encoded.toByteArray(), StandardOpenOption.CREATE_NEW);
            return new ArchivoUploadResponse("/uploads/noticias/" + filename, filename, "image/" + format, encoded.size());
        } catch (IOException e) {
            throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR, "No se pudo guardar la imagen");
        }
    }
    private ResponseStatusException invalida() {
        return new ResponseStatusException(HttpStatus.BAD_REQUEST, "Seleccioná una imagen JPEG o PNG válida de hasta 5 MB y 20 megapíxeles");
    }
}
