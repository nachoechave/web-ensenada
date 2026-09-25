package ar.gov.ensenada.backend.contenido;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Configuration;
import org.springframework.web.servlet.config.annotation.ResourceHandlerRegistry;
import org.springframework.web.servlet.config.annotation.WebMvcConfigurer;

import java.nio.file.Path;

@Configuration
public class ArchivosStaticConfig implements WebMvcConfigurer {

    @Value("${app.uploads.dir:uploads}")
    private String uploadsDir;

    @Override
    public void addResourceHandlers(ResourceHandlerRegistry registry) {
        registrar(registry, "noticias");
        registrar(registry, "sitio");
    }

    private void registrar(ResourceHandlerRegistry registry, String carpeta) {
        String ubicacion = Path.of(uploadsDir, carpeta).toAbsolutePath().normalize().toUri().toString();
        registry.addResourceHandler("/uploads/" + carpeta + "/**")
                .addResourceLocations(ubicacion.endsWith("/") ? ubicacion : ubicacion + "/");
    }
}
