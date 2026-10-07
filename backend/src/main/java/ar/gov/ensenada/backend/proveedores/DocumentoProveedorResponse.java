package ar.gov.ensenada.backend.proveedores;

public record DocumentoProveedorResponse(
        Long id,
        String titulo,
        String descripcion,
        String nombreOriginal,
        String url,
        String tipoMime,
        long tamanio,
        int orden,
        boolean activo
) {
    public static DocumentoProveedorResponse from(DocumentoProveedor d) {
        return new DocumentoProveedorResponse(
                d.getId(),
                d.getTitulo(),
                d.getDescripcion(),
                d.getNombreOriginal(),
                "/uploads/proveedores/" + d.getNombreAlmacenado(),
                d.getTipoMime(),
                d.getTamanio(),
                d.getOrden(),
                d.isActivo()
        );
    }
}
