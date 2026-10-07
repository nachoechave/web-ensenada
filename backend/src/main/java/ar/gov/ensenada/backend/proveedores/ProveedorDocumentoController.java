package ar.gov.ensenada.backend.proveedores;

import jakarta.validation.Valid;
import jakarta.validation.constraints.NotNull;
import jakarta.validation.constraints.Size;
import org.springframework.core.io.Resource;
import org.springframework.http.*;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;

import java.nio.charset.StandardCharsets;
import java.util.List;

@RestController
public class ProveedorDocumentoController {
    private final ProveedorDocumentoService service;
    private final ProveedorStorage storage;

    public ProveedorDocumentoController(ProveedorDocumentoService service, ProveedorStorage storage) {
        this.service = service;
        this.storage = storage;
    }

    @GetMapping("/api/proveedores/documentos")
    public List<DocumentoProveedorResponse> listarPublico() {
        return service.listar(true);
    }

    @GetMapping("/api/admin/proveedores/documentos")
    public List<DocumentoProveedorResponse> listarAdmin() {
        return service.listar(false);
    }

    @PostMapping(value="/api/admin/proveedores/documentos", consumes=MediaType.MULTIPART_FORM_DATA_VALUE)
    @ResponseStatus(HttpStatus.CREATED)
    public DocumentoProveedorResponse crear(
            @RequestParam String titulo,
            @RequestParam(defaultValue="") String descripcion,
            @RequestParam("archivo") MultipartFile archivo) {
        return service.crear(titulo, descripcion, archivo);
    }

    public record EditarRequest(String titulo, String descripcion, boolean activo) {}

    @PutMapping("/api/admin/proveedores/documentos/{id}")
    public DocumentoProveedorResponse editar(@PathVariable Long id, @RequestBody EditarRequest request) {
        return service.editar(id, request.titulo(), request.descripcion(), request.activo());
    }

    @DeleteMapping("/api/admin/proveedores/documentos/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void eliminar(@PathVariable Long id) {
        service.eliminar(id);
    }

    public record OrdenRequest(@NotNull @Size(max=100) List<@NotNull Long> ids) {}

    @PutMapping("/api/admin/proveedores/documentos/orden")
    public List<DocumentoProveedorResponse> ordenar(@Valid @RequestBody OrdenRequest request) {
        return service.ordenar(request.ids());
    }

    @GetMapping("/uploads/proveedores/{nombre}")
    public ResponseEntity<Resource> descargar(@PathVariable String nombre) {
        var d = service.archivoPublico(nombre);
        return ResponseEntity.ok()
                .contentType(MediaType.parseMediaType(d.getTipoMime()))
                .contentLength(d.getTamanio())
                .cacheControl(CacheControl.noStore())
                .header("X-Content-Type-Options", "nosniff")
                .header(HttpHeaders.CONTENT_DISPOSITION,
                        ContentDisposition.attachment().filename(d.getNombreOriginal(), StandardCharsets.UTF_8).build().toString())
                .body(storage.leer(d.getNombreAlmacenado()));
    }
}
