package ar.gov.ensenada.backend.archivos;

import org.springframework.http.HttpStatus;
import org.springframework.web.server.ResponseStatusException;
import org.springframework.web.multipart.MultipartFile;
import javax.imageio.*;
import javax.imageio.stream.ImageInputStream;
import java.awt.image.BufferedImage;
import java.io.*;
import java.util.*;

/** Shared image validation; neither editorial domain depends on the other. */
public final class ImagenSegura {
    private ImagenSegura() {}
    public record Resultado(byte[] bytes, String extension, String mime) {}
    public static Resultado procesar(MultipartFile archivo) {
        if (archivo.isEmpty() || archivo.getSize() > 5 * 1024 * 1024) throw invalida();
        BufferedImage image;
        String format;
        try (InputStream input=archivo.getInputStream(); ImageInputStream stream=ImageIO.createImageInputStream(input)) {
            Iterator<ImageReader> readers=ImageIO.getImageReaders(stream);
            if (!readers.hasNext()) throw invalida();
            ImageReader reader=readers.next();
            try {
                format=reader.getFormatName().toLowerCase(Locale.ROOT);
                if (!Set.of("jpeg","png").contains(format)) throw invalida();
                reader.setInput(stream,true,true);
                int width=reader.getWidth(0), height=reader.getHeight(0);
                if (width<1 || height<1 || (long)width*height>20_000_000) throw invalida();
                image=reader.read(0);
                if (image==null) throw invalida();
            } finally {reader.dispose();}
            ByteArrayOutputStream encoded=new ByteArrayOutputStream();
            if (!ImageIO.write(image,format,encoded) || encoded.size()>5*1024*1024) throw invalida();
            return new Resultado(encoded.toByteArray(),format.equals("jpeg")?"jpg":"png","image/"+format);
        } catch(IOException | IllegalArgumentException e){throw invalida();}
    }
    private static ResponseStatusException invalida(){
        return new ResponseStatusException(HttpStatus.BAD_REQUEST,"Seleccioná un JPEG/PNG válido de hasta 5 MB y 20 megapíxeles");
    }
}
