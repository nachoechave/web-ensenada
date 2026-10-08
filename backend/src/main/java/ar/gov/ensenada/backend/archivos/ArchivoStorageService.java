package ar.gov.ensenada.backend.archivos;

import java.io.IOException;
import java.net.URI;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.StandardOpenOption;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.web.server.ResponseStatusException;

import software.amazon.awssdk.auth.credentials.AwsBasicCredentials;
import software.amazon.awssdk.auth.credentials.StaticCredentialsProvider;
import software.amazon.awssdk.core.sync.RequestBody;
import software.amazon.awssdk.regions.Region;
import software.amazon.awssdk.services.s3.S3Client;
import software.amazon.awssdk.services.s3.model.GetObjectRequest;
import software.amazon.awssdk.services.s3.model.PutObjectRequest;
import software.amazon.awssdk.services.s3.model.NoSuchKeyException;

@Service
public class ArchivoStorageService implements AutoCloseable {
    private final boolean r2;
    private final String bucket;
    private final Path localRoot;
    private final S3Client client;

    public ArchivoStorageService(
            @Value("${app.storage.provider:local}") String provider,
            @Value("${app.uploads.dir:uploads}") String localDir,
            @Value("${app.r2.endpoint:}") String endpoint,
            @Value("${app.r2.bucket:}") String bucket,
            @Value("${app.r2.access-key-id:}") String accessKey,
            @Value("${app.r2.secret-access-key:}") String secretKey) {
        this.r2 = "r2".equalsIgnoreCase(provider);
        if (!this.r2 && !"local".equalsIgnoreCase(provider)) {
            throw new IllegalArgumentException("STORAGE_PROVIDER debe ser local o r2");
        }
        this.localRoot = Path.of(localDir).toAbsolutePath().normalize();
        this.bucket = bucket;
        if (r2) {
            if (endpoint.isBlank() || bucket.isBlank() || accessKey.isBlank() || secretKey.isBlank()) {
                throw new IllegalArgumentException("Faltan variables R2_ENDPOINT, R2_BUCKET, R2_ACCESS_KEY_ID o R2_SECRET_ACCESS_KEY");
            }
            this.client = S3Client.builder()
                    .endpointOverride(URI.create(endpoint))
                    .region(Region.of("auto"))
                    .forcePathStyle(true)
                    .credentialsProvider(StaticCredentialsProvider.create(AwsBasicCredentials.create(accessKey, secretKey)))
                    .build();
        } else {
            this.client = null;
        }
    }

    public void guardar(String categoria, String nombre, byte[] bytes, String mime) {
        validarRuta(categoria, nombre);
        try {
            if (r2) {
                client.putObject(PutObjectRequest.builder().bucket(bucket)
                        .key(categoria + "/" + nombre).contentType(mime).build(), RequestBody.fromBytes(bytes));
            } else {
                Path dir = localRoot.resolve(categoria);
                Files.createDirectories(dir);
                Files.write(dir.resolve(nombre), bytes, StandardOpenOption.CREATE_NEW);
            }
        } catch (Exception ex) {
            throw new ResponseStatusException(HttpStatus.INTERNAL_SERVER_ERROR, "No se pudo almacenar el archivo", ex);
        }
    }

    public byte[] leer(String categoria, String nombre) {
        validarRuta(categoria, nombre);
        try {
            if (r2) {
                return client.getObjectAsBytes(GetObjectRequest.builder().bucket(bucket)
                        .key(categoria + "/" + nombre).build()).asByteArray();
            }
            Path archivo = localRoot.resolve(categoria).resolve(nombre);
            if (!Files.isRegularFile(archivo)) {
                throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Archivo no encontrado");
            }
            return Files.readAllBytes(archivo);
        } catch (NoSuchKeyException ex) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Archivo no encontrado", ex);
        } catch (ResponseStatusException ex) {
            throw ex;
        } catch (Exception ex) {
            throw new ResponseStatusException(HttpStatus.BAD_GATEWAY, "No se pudo recuperar el archivo", ex);
        }
    }

    private void validarRuta(String categoria, String nombre) {
        if (!("noticias".equals(categoria) || "sitio".equals(categoria))
                || !nombre.matches("[a-zA-Z0-9._-]+") || nombre.equals(".") || nombre.equals("..")) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Ruta no permitida");
        }
    }

    @Override
    public void close() {
        if (client != null) client.close();
    }
}
