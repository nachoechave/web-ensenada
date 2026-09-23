package ar.gov.ensenada.backend.contenido;

public record ArchivoUploadResponse(
        String url,
        String nombreOriginal,
        String contentType,
        long size
) {
}
