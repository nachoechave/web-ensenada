package ar.gov.ensenada.backend.archivos;

import org.junit.jupiter.api.Test;
import org.springframework.mock.web.MockMultipartFile;
import org.springframework.web.server.ResponseStatusException;

import javax.imageio.ImageIO;
import java.awt.image.BufferedImage;
import java.io.ByteArrayInputStream;
import java.io.ByteArrayOutputStream;

import static org.junit.jupiter.api.Assertions.*;

class ImagenSitioOptimizadaTests {

    @Test
    void redimensionaYConvierteImagenGrande() throws Exception {
        BufferedImage image = new BufferedImage(3200, 1800, BufferedImage.TYPE_INT_RGB);
        for (int y = 0; y < image.getHeight(); y += 40) {
            for (int x = 0; x < image.getWidth(); x += 40) {
                int rgb = ((x * 31) ^ (y * 17)) & 0x00ffffff;
                for (int yy = y; yy < Math.min(y + 40, image.getHeight()); yy++) {
                    for (int xx = x; xx < Math.min(x + 40, image.getWidth()); xx++) {
                        image.setRGB(xx, yy, rgb);
                    }
                }
            }
        }

        ByteArrayOutputStream source = new ByteArrayOutputStream();
        assertTrue(ImageIO.write(image, "png", source));
        MockMultipartFile file = new MockMultipartFile(
                "archivo",
                "foto.png",
                "image/png",
                source.toByteArray()
        );

        ImagenSitioOptimizada.Resultado result = ImagenSitioOptimizada.procesar(file);

        assertEquals("jpg", result.extension());
        assertEquals("image/jpeg", result.mime());
        assertTrue(result.bytes().length <= 5 * 1024 * 1024);

        BufferedImage output = ImageIO.read(new ByteArrayInputStream(result.bytes()));
        assertNotNull(output);
        assertEquals(2560, Math.max(output.getWidth(), output.getHeight()));
    }

    @Test
    void conservaTransparenciaEnPng() throws Exception {
        BufferedImage image = new BufferedImage(200, 120, BufferedImage.TYPE_INT_ARGB);
        image.setRGB(10, 10, 0x55ff0000);

        ByteArrayOutputStream source = new ByteArrayOutputStream();
        assertTrue(ImageIO.write(image, "png", source));
        MockMultipartFile file = new MockMultipartFile(
                "archivo",
                "transparente.png",
                "image/png",
                source.toByteArray()
        );

        ImagenSitioOptimizada.Resultado result = ImagenSitioOptimizada.procesar(file);

        assertEquals("png", result.extension());
        assertEquals("image/png", result.mime());
        BufferedImage output = ImageIO.read(new ByteArrayInputStream(result.bytes()));
        assertTrue(output.getColorModel().hasAlpha());
    }

    @Test
    void rechazaArchivoQueNoEsImagen() {
        MockMultipartFile file = new MockMultipartFile(
                "archivo",
                "falso.jpg",
                "image/jpeg",
                "esto no es una imagen".getBytes()
        );

        assertThrows(ResponseStatusException.class, () -> ImagenSitioOptimizada.procesar(file));
    }
}
