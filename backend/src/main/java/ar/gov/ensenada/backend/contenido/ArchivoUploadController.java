package ar.gov.ensenada.backend.contenido;
import ar.gov.ensenada.backend.archivos.ImagenSegura;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.*;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;
import java.io.IOException;
import java.nio.file.*;
import java.util.UUID;

@RestController
@RequestMapping("/api/admin/archivos")
public class ArchivoUploadController {
    @Value("${app.uploads.dir:uploads}") private String uploadsDir;
    @PostMapping(value="/noticias", consumes=MediaType.MULTIPART_FORM_DATA_VALUE)
    public ArchivoUploadResponse subir(@RequestParam("archivo") MultipartFile archivo) {
        var image=ImagenSegura.procesar(archivo);
        String filename=UUID.randomUUID()+"."+image.extension();
        Path directory=Path.of(uploadsDir,"noticias").toAbsolutePath().normalize();
        try {
            Files.createDirectories(directory);
            Files.write(directory.resolve(filename),image.bytes(),StandardOpenOption.CREATE_NEW);
            return new ArchivoUploadResponse("/uploads/noticias/"+filename,filename,image.mime(),image.bytes().length);
        } catch(IOException e){throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR,"No se pudo guardar la imagen");}
    }
}
