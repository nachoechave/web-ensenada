package ar.gov.ensenada.backend.hacienda;

import ar.gov.ensenada.backend.archivos.ImagenSegura;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.core.io.Resource;
import org.springframework.core.io.FileSystemResource;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;
import org.springframework.transaction.support.TransactionSynchronization;
import org.springframework.transaction.support.TransactionSynchronizationManager;
import java.io.*;
import java.nio.charset.StandardCharsets;
import java.nio.file.*;
import java.util.UUID;

@Service
public class HaciendaStorage {
    private final Path directory;
    public HaciendaStorage(@Value("${app.uploads.dir:uploads}") String uploads){directory=Path.of(uploads,"hacienda").toAbsolutePath().normalize();}
    public record Guardado(String nombre, String original, String mime, long tamanio) {}
    public Guardado guardar(MultipartFile file){
        if(file.isEmpty() || file.getSize()>20*1024*1024) throw new ResponseStatusException(HttpStatus.BAD_REQUEST,"Máximo 20 MB por PDF; imágenes hasta 5 MB");
        try {
            byte[] bytes;
            String extension,mime;
            byte[] signature;
            try(InputStream input=file.getInputStream()){signature=input.readNBytes(5);}
            if(java.util.Arrays.equals(signature,"%PDF-".getBytes(StandardCharsets.US_ASCII))){
                bytes=file.getBytes();
                String tail=new String(bytes,Math.max(0,bytes.length-1024),Math.min(1024,bytes.length),StandardCharsets.ISO_8859_1);
                if(!tail.contains("%%EOF")) throw new ResponseStatusException(HttpStatus.BAD_REQUEST,"PDF incompleto");
                extension="pdf"; mime="application/pdf";
            } else {
                var image=ImagenSegura.procesar(file); bytes=image.bytes(); extension=image.extension(); mime=image.mime();
            }
            String name=UUID.randomUUID()+"."+extension;
            Files.createDirectories(directory);
            Path destination=resolver(name);
            Files.write(destination,bytes,StandardOpenOption.CREATE_NEW);
            // Database rollback must not leave a newly uploaded orphan behind.
            if(TransactionSynchronizationManager.isSynchronizationActive()) TransactionSynchronizationManager.registerSynchronization(new TransactionSynchronization(){
                @Override public void afterCompletion(int status){if(status!=STATUS_COMMITTED) try{Files.deleteIfExists(destination);}catch(IOException ignored){}}
            });
            String original=file.getOriginalFilename()==null?"Documento":file.getOriginalFilename();
            original=original.replace('\\','/'); original=original.substring(original.lastIndexOf('/')+1).replaceAll("[\\p{Cntrl}]","");
            if(original.isBlank()) original="Documento";
            if(original.length()>255) original=original.substring(0,255);
            return new Guardado(name,original,mime,bytes.length);
        } catch(IOException e){throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR,"No se pudo guardar el documento");}
    }
    private Path resolver(String name){
        if(!name.matches("[a-f0-9-]{36}\\.(pdf|jpg|png)")) throw new ResponseStatusException(HttpStatus.NOT_FOUND);
        Path result=directory.resolve(name).normalize();
        if(!result.startsWith(directory)) throw new ResponseStatusException(HttpStatus.NOT_FOUND);
        return result;
    }
    public Resource leer(String name){
        Path file=resolver(name);
        if(!Files.isRegularFile(file,LinkOption.NOFOLLOW_LINKS)) throw new ResponseStatusException(HttpStatus.NOT_FOUND);
        return new FileSystemResource(file);
    }
}
