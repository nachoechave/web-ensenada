package ar.gov.ensenada.backend.proveedores;

import org.springframework.http.HttpStatus;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.web.multipart.MultipartFile;
import org.springframework.web.server.ResponseStatusException;

import java.util.*;

@Service
@Transactional
public class ProveedorDocumentoService {
    private final DocumentoProveedorRepository repository;
    private final ProveedorStorage storage;

    public ProveedorDocumentoService(DocumentoProveedorRepository repository, ProveedorStorage storage) {
        this.repository = repository;
        this.storage = storage;
    }

    @Transactional(readOnly = true)
    public List<DocumentoProveedorResponse> listar(boolean publico) {
        var rows = publico ? repository.findByActivoTrueOrderByOrdenAscIdAsc() : repository.findAllByOrderByOrdenAscIdAsc();
        return rows.stream().map(DocumentoProveedorResponse::from).toList();
    }

    public DocumentoProveedorResponse crear(String titulo, String descripcion, MultipartFile archivo) {
        validarTexto(titulo, descripcion);
        var guardado = storage.guardar(archivo);
        int orden = repository.findAllByOrderByOrdenAscIdAsc().size();
        var row = new DocumentoProveedor(titulo.trim(), descripcion.trim(), guardado.original(), guardado.nombre(),
                guardado.mime(), guardado.tamanio(), orden);
        return DocumentoProveedorResponse.from(repository.save(row));
    }

    public DocumentoProveedorResponse editar(Long id, String titulo, String descripcion, boolean activo) {
        validarTexto(titulo, descripcion);
        var row = obtener(id);
        row.editar(titulo.trim(), descripcion.trim(), activo);
        return DocumentoProveedorResponse.from(row);
    }

    public void eliminar(Long id) {
        var row = obtener(id);
        repository.delete(row);
        reordenar();
    }

    public List<DocumentoProveedorResponse> ordenar(List<Long> ids) {
        var actuales = repository.findAllByOrderByOrdenAscIdAsc();
        Set<Long> existentes = new HashSet<>();
        actuales.forEach(d -> existentes.add(d.getId()));
        if (ids.size() != existentes.size() || !new HashSet<>(ids).equals(existentes)) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "El orden debe incluir todos los documentos");
        }
        actuales.forEach(d -> d.setOrden(ids.indexOf(d.getId())));
        return repository.findAllByOrderByOrdenAscIdAsc().stream().map(DocumentoProveedorResponse::from).toList();
    }

    @Transactional(readOnly = true)
    public DocumentoProveedor archivoPublico(String nombre) {
        return repository.findByNombreAlmacenado(nombre)
                .filter(DocumentoProveedor::isActivo)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND));
    }

    private DocumentoProveedor obtener(Long id) {
        return repository.findById(id).orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Documento no encontrado"));
    }

    private void validarTexto(String titulo, String descripcion) {
        if (titulo == null || titulo.trim().isBlank() || titulo.length() > 180) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "Ingresá un título válido");
        }
        if (descripcion == null || descripcion.length() > 500) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "La descripción supera el máximo permitido");
        }
    }

    private void reordenar() {
        var rows = repository.findAllByOrderByOrdenAscIdAsc();
        for (int i=0;i<rows.size();i++) rows.get(i).setOrden(i);
    }
}
