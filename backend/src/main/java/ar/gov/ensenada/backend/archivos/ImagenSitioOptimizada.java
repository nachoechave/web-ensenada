package ar.gov.ensenada.backend.archivos;

import org.springframework.http.HttpStatus;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;

import javax.imageio.IIOImage;
import javax.imageio.ImageIO;
import javax.imageio.ImageReader;
import javax.imageio.ImageWriteParam;
import javax.imageio.ImageWriter;
import javax.imageio.stream.ImageInputStream;
import javax.imageio.stream.ImageOutputStream;
import java.awt.Graphics2D;
import java.awt.RenderingHints;
import java.awt.Transparency;
import java.awt.image.BufferedImage;
import java.io.ByteArrayOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.util.Iterator;

/**
 * Procesa imágenes del CMS público. Acepta cualquier formato raster que ImageIO
 * pueda decodificar y lo normaliza a JPEG o PNG, quitando metadatos, reduciendo
 * dimensiones y peso antes de guardarlo.
 */
public final class ImagenSitioOptimizada {
    private static final long MAX_INPUT_BYTES = 30L * 1024 * 1024;
    private static final long MAX_PIXELS = 60_000_000L;
    private static final int MAX_DIMENSION = 2560;
    private static final int MAX_OUTPUT_BYTES = 5 * 1024 * 1024;

    private ImagenSitioOptimizada() {}

    public record Resultado(byte[] bytes, String extension, String mime) {}

    public static Resultado procesar(MultipartFile archivo) {
        if (archivo.isEmpty() || archivo.getSize() > MAX_INPUT_BYTES) {
            throw invalida("La imagen debe pesar hasta 30 MB antes de optimizarse");
        }

        try (InputStream input = archivo.getInputStream();
             ImageInputStream stream = ImageIO.createImageInputStream(input)) {
            if (stream == null) throw invalida("El archivo no es una imagen compatible");

            Iterator<ImageReader> readers = ImageIO.getImageReaders(stream);
            if (!readers.hasNext()) throw invalida("El archivo no es una imagen compatible");

            ImageReader reader = readers.next();
            BufferedImage original;
            try {
                reader.setInput(stream, true, true);
                int width = reader.getWidth(0);
                int height = reader.getHeight(0);
                if (width < 1 || height < 1 || (long) width * height > MAX_PIXELS) {
                    throw invalida("La imagen supera el límite de resolución permitido");
                }
                original = reader.read(0);
                if (original == null) throw invalida("El archivo no es una imagen compatible");
            } finally {
                reader.dispose();
            }

            BufferedImage optimizada = redimensionar(original, MAX_DIMENSION);
            boolean transparencia = optimizada.getColorModel().hasAlpha()
                    && optimizada.getTransparency() != Transparency.OPAQUE;

            if (transparencia) {
                byte[] png = comprimirPng(optimizada);
                while (png.length > MAX_OUTPUT_BYTES && optimizada.getWidth() > 900 && optimizada.getHeight() > 900) {
                    optimizada = escalar(optimizada, 0.82);
                    png = comprimirPng(optimizada);
                }
                if (png.length > MAX_OUTPUT_BYTES) {
                    throw invalida("No se pudo reducir la imagen a un tamaño seguro");
                }
                return new Resultado(png, "png", "image/png");
            }

            byte[] jpg = comprimirJpeg(optimizada, 0.86f);
            float calidad = 0.78f;
            while (jpg.length > MAX_OUTPUT_BYTES && calidad >= 0.54f) {
                jpg = comprimirJpeg(optimizada, calidad);
                calidad -= 0.08f;
            }
            while (jpg.length > MAX_OUTPUT_BYTES && optimizada.getWidth() > 900 && optimizada.getHeight() > 900) {
                optimizada = escalar(optimizada, 0.82);
                jpg = comprimirJpeg(optimizada, 0.76f);
            }
            if (jpg.length > MAX_OUTPUT_BYTES) {
                throw invalida("No se pudo reducir la imagen a un tamaño seguro");
            }
            return new Resultado(jpg, "jpg", "image/jpeg");
        } catch (ResponseStatusException e) {
            throw e;
        } catch (IOException | IllegalArgumentException e) {
            throw invalida("No se pudo procesar la imagen");
        }
    }

    private static BufferedImage redimensionar(BufferedImage original, int maxDimension) {
        int width = original.getWidth();
        int height = original.getHeight();
        int mayor = Math.max(width, height);
        if (mayor <= maxDimension) return original;

        double escala = (double) maxDimension / mayor;
        int nuevoAncho = Math.max(1, (int) Math.round(width * escala));
        int nuevoAlto = Math.max(1, (int) Math.round(height * escala));
        return escalar(original, nuevoAncho, nuevoAlto);
    }

    private static BufferedImage escalar(BufferedImage original, double factor) {
        int nuevoAncho = Math.max(1, (int) Math.round(original.getWidth() * factor));
        int nuevoAlto = Math.max(1, (int) Math.round(original.getHeight() * factor));
        return escalar(original, nuevoAncho, nuevoAlto);
    }

    private static BufferedImage escalar(BufferedImage original, int width, int height) {
        int tipo = original.getColorModel().hasAlpha() ? BufferedImage.TYPE_INT_ARGB : BufferedImage.TYPE_INT_RGB;
        BufferedImage resized = new BufferedImage(width, height, tipo);
        Graphics2D graphics = resized.createGraphics();
        try {
            graphics.setRenderingHint(RenderingHints.KEY_INTERPOLATION, RenderingHints.VALUE_INTERPOLATION_BICUBIC);
            graphics.setRenderingHint(RenderingHints.KEY_RENDERING, RenderingHints.VALUE_RENDER_QUALITY);
            graphics.setRenderingHint(RenderingHints.KEY_ANTIALIASING, RenderingHints.VALUE_ANTIALIAS_ON);
            graphics.drawImage(original, 0, 0, width, height, null);
        } finally {
            graphics.dispose();
        }
        return resized;
    }

    private static byte[] comprimirPng(BufferedImage image) throws IOException {
        ByteArrayOutputStream output = new ByteArrayOutputStream();
        if (!ImageIO.write(image, "png", output)) {
            throw invalida("No se pudo convertir la imagen a PNG");
        }
        return output.toByteArray();
    }

    private static byte[] comprimirJpeg(BufferedImage image, float calidad) throws IOException {
        BufferedImage rgb = image.getType() == BufferedImage.TYPE_INT_RGB ? image : convertirRgb(image);

        Iterator<ImageWriter> writers = ImageIO.getImageWritersByFormatName("jpeg");
        if (!writers.hasNext()) throw invalida("No hay un codificador JPEG disponible");

        ImageWriter writer = writers.next();
        try (ByteArrayOutputStream output = new ByteArrayOutputStream();
             ImageOutputStream imageOutput = ImageIO.createImageOutputStream(output)) {
            writer.setOutput(imageOutput);
            ImageWriteParam params = writer.getDefaultWriteParam();
            if (params.canWriteCompressed()) {
                params.setCompressionMode(ImageWriteParam.MODE_EXPLICIT);
                params.setCompressionQuality(calidad);
            }
            writer.write(null, new IIOImage(rgb, null, null), params);
            imageOutput.flush();
            return output.toByteArray();
        } finally {
            writer.dispose();
        }
    }

    private static BufferedImage convertirRgb(BufferedImage image) {
        BufferedImage rgb = new BufferedImage(image.getWidth(), image.getHeight(), BufferedImage.TYPE_INT_RGB);
        Graphics2D graphics = rgb.createGraphics();
        try {
            graphics.drawImage(image, 0, 0, null);
        } finally {
            graphics.dispose();
        }
        return rgb;
    }

    private static ResponseStatusException invalida(String detalle) {
        return new ResponseStatusException(
                HttpStatus.BAD_REQUEST,
                detalle + ". Usá una imagen raster común de hasta 30 MB"
        );
    }
}
