package ar.gov.ensenada.backend.archivos;

import ar.gov.ensenada.backend.contenido.NoticiaRepository;
import ar.gov.ensenada.backend.contenido.PortalSiteContentRepository;
import ar.gov.ensenada.backend.hacienda.ArchivoHaciendaRepository;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.scheduling.annotation.Scheduled;
import org.springframework.stereotype.Service;

import java.io.IOException;
import java.nio.file.Files;
import java.nio.file.LinkOption;
import java.nio.file.Path;
import java.time.Duration;
import java.time.Instant;
import java.util.HashSet;
import java.util.Set;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import java.util.stream.Stream;

@Service
public class OrphanUploadCleanupService {

    private static final Logger log = LoggerFactory.getLogger(OrphanUploadCleanupService.class);
    private static final Pattern SITE_UPLOAD = Pattern.compile("/uploads/sitio/([a-f0-9-]{36}\\.(?:jpg|png))");

    private final Path uploadsRoot;
    private final NoticiaRepository noticias;
    private final ArchivoHaciendaRepository archivosHacienda;
    private final PortalSiteContentRepository portalContent;
    private final boolean enabled;
    private final Duration retention;

    public OrphanUploadCleanupService(
            @Value("${app.uploads.dir:uploads}") String uploadsDir,
            NoticiaRepository noticias,
            ArchivoHaciendaRepository archivosHacienda,
            PortalSiteContentRepository portalContent,
            @Value("${app.upload-cleanup.enabled:true}") boolean enabled,
            @Value("${app.upload-cleanup.retention-hours:168}") long retentionHours
    ) {
        this.uploadsRoot = Path.of(uploadsDir).toAbsolutePath().normalize();
        this.noticias = noticias;
        this.archivosHacienda = archivosHacienda;
        this.portalContent = portalContent;
        this.enabled = enabled;
        this.retention = Duration.ofHours(Math.max(24, retentionHours));
    }

    @Scheduled(cron = "${app.upload-cleanup.cron:0 30 3 * * *}")
    public void limpiar() {
        if (!enabled) return;

        Instant cutoff = Instant.now().minus(retention);
        limpiarCarpeta("noticias", referenciasNoticias(), cutoff);
        limpiarCarpeta("hacienda", referenciasHacienda(), cutoff);
        limpiarCarpeta("sitio", referenciasSitio(), cutoff);
    }

    private Set<String> referenciasNoticias() {
        Set<String> referencias = new HashSet<>();
        noticias.findAll().forEach(noticia -> {
            agregarNombreDesdeUrl(referencias, noticia.getImagen(), "/uploads/noticias/");
            if (noticia.getImagenes() != null) {
                noticia.getImagenes().forEach(url -> agregarNombreDesdeUrl(referencias, url, "/uploads/noticias/"));
            }
        });
        return referencias;
    }

    private Set<String> referenciasHacienda() {
        Set<String> referencias = new HashSet<>();
        archivosHacienda.findAll().forEach(archivo -> referencias.add(archivo.getNombreAlmacenado()));
        return referencias;
    }

    private Set<String> referenciasSitio() {
        Set<String> referencias = new HashSet<>();
        portalContent.findAll().forEach(content -> {
            Matcher matcher = SITE_UPLOAD.matcher(content.getContenidoJson());
            while (matcher.find()) referencias.add(matcher.group(1));
        });
        return referencias;
    }

    private void agregarNombreDesdeUrl(Set<String> referencias, String value, String prefix) {
        if (value == null || !value.startsWith(prefix)) return;
        String nombre = value.substring(prefix.length());
        if (!nombre.isBlank() && !nombre.contains("/")) referencias.add(nombre);
    }

    private void limpiarCarpeta(String carpeta, Set<String> referencias, Instant cutoff) {
        Path directory = uploadsRoot.resolve(carpeta).normalize();
        if (!directory.startsWith(uploadsRoot) || !Files.isDirectory(directory, LinkOption.NOFOLLOW_LINKS)) return;

        int eliminados = 0;
        try (Stream<Path> files = Files.list(directory)) {
            for (Path file : files.toList()) {
                if (!Files.isRegularFile(file, LinkOption.NOFOLLOW_LINKS)) continue;
                String nombre = file.getFileName().toString();
                if (referencias.contains(nombre)) continue;
                if (Files.getLastModifiedTime(file, LinkOption.NOFOLLOW_LINKS).toInstant().isAfter(cutoff)) continue;

                Files.deleteIfExists(file);
                eliminados++;
            }
        } catch (IOException e) {
            log.warn("No se pudo completar la limpieza de uploads en {}", directory, e);
            return;
        }

        if (eliminados > 0) {
            log.info("Limpieza de uploads: se eliminaron {} archivos huérfanos de {}", eliminados, carpeta);
        }
    }
}
