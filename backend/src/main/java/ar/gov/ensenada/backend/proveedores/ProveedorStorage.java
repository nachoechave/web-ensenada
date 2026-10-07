package ar.gov.ensenada.backend.proveedores;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.core.io.FileSystemResource;
import org.springframework.core.io.Resource;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;

import java.io.IOException;
import java.nio.file.*;
import java.util.Locale;
import java.util.UUID;

@Service
public class ProveedorStorage {
    private final Path directory;

    public ProveedorStorage(@Value("${app.uploads.dir:uploads}") String uploads) {
        this.directory = Path.of(uploads, "proveedores").toAbsolutePath().normalize();
    }

    public record Guardado(String nombre, String original, String mime, long tamanio) {}

    public Guardado guardar(MultipartFile file) {
        if (file.isEmpty() || file.getSize() > 20 * 1024 * 1024) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El archivo debe pesar menos de 20 MB");
        }

        String original = file.getOriginalFilename() == null ? "Documento" : file.getOriginalFilename();
        original = original.replace('\\','/');
        original = original.substring(original.lastIndexOf('/') + 1).replaceAll("[\\p{Cntrl}]", "");
        if (original.isBlank()) original = "Documento";
        if (original.length() > 255) original = original.substring(0, 255);

        String lower = original.toLowerCase(Locale.ROOT);
        String extension;
        String mime;
        if (lower.endsWith(".pdf")) {
            extension = "pdf";
            mime = "application/pdf";
        } else if (lower.endsWith(".xlsx")) {
            extension = "xlsx";
            mime = "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet";
        } else if (lower.endsWith(".xls")) {
            extension = "xls";
            mime = "application/vnd.ms-excel";
        } else if (lower.endsWith(".doc")) {
            extension = "doc";
            mime = "application/msword";
        } else {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Formato no permitido. Usá PDF, DOC, XLS o XLSX");
        }

        try {
            byte[] bytes = file.getBytes();
            validarFirma(bytes, extension);
            Files.createDirectories(directory);
            String nombre = UUID.randomUUID() + "." + extension;
            Files.write(resolver(nombre), bytes, StandardOpenOption.CREATE_NEW);
            return new Guardado(nombre, original, mime, bytes.length);
        } catch (IOException e) {
            throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR, "No se pudo guardar el archivo");
        }
    }

    private void validarFirma(byte[] bytes, String extension) {
        if ("pdf".equals(extension)) {
            if (bytes.length < 5 || bytes[0] != '%' || bytes[1] != 'P' || bytes[2] != 'D' || bytes[3] != 'F' || bytes[4] != '-') {
                throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El archivo no es un PDF válido");
            }
        } else if ("xlsx".equals(extension)) {
            if (bytes.length < 4 || bytes[0] != 'P' || bytes[1] != 'K') {
                throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El archivo no es un XLSX válido");
            }
        } else if ("xls".equals(extension) || "doc".equals(extension)) {
            byte[] magic = {(byte)0xD0,(byte)0xCF,0x11,(byte)0xE0,(byte)0xA1,(byte)0xB1,0x1A,(byte)0xE1};
            if (bytes.length < magic.length) throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El archivo no es un documento Office válido");
            for (int i=0;i<magic.length;i++) if (bytes[i] != magic[i]) {
                throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El archivo no es un documento Office válido");
            }
        }
    }

    private Path resolver(String nombre) {
        if (!nombre.matches("[a-f0-9-]{36}\\.(pdf|doc|xls|xlsx)")) throw new ResponseStatusException(HttpStatus.NOT_FOUND);
        Path result = directory.resolve(nombre).normalize();
        if (!result.startsWith(directory)) throw new ResponseStatusException(HttpStatus.NOT_FOUND);
        return result;
    }

    public Resource leer(String nombre) {
        Path file = resolver(nombre);
        if (!Files.isRegularFile(file, LinkOption.NOFOLLOW_LINKS)) throw new ResponseStatusException(HttpStatus.NOT_FOUND);
        return new FileSystemResource(file);
    }
}
