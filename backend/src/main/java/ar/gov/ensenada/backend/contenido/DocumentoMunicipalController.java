package ar.gov.ensenada.backend.contenido;

import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;

@RestController
@RequestMapping("/api")
public class DocumentoMunicipalController {

    private final DocumentoMunicipalRepository documentoRepository;

    public DocumentoMunicipalController(DocumentoMunicipalRepository documentoRepository) {
        this.documentoRepository = documentoRepository;
    }

    @GetMapping("/hacienda")
    public List<DocumentoMunicipal> listarHacienda() {
        return listarPorTipo(TipoDocumentoMunicipal.HACIENDA, null);
    }

    @GetMapping("/admin/hacienda")
    public List<DocumentoMunicipal> listarHaciendaAdmin() {
        return listarPorTipo(TipoDocumentoMunicipal.HACIENDA, null);
    }

    @PostMapping("/admin/hacienda")
    @ResponseStatus(HttpStatus.CREATED)
    public DocumentoMunicipal crearHacienda(@Valid @RequestBody DocumentoMunicipalRequest request) {
        return crear(request, TipoDocumentoMunicipal.HACIENDA);
    }

    @DeleteMapping("/admin/hacienda/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void eliminarHacienda(@PathVariable Long id) {
        eliminar(id, TipoDocumentoMunicipal.HACIENDA);
    }

    private List<DocumentoMunicipal> listarPorTipo(TipoDocumentoMunicipal tipo, Integer anio) {
        if (anio == null) {
            return documentoRepository.findByTipoOrderByIdDesc(tipo);
        }

        return documentoRepository.findByTipoAndAnioOrderByIdDesc(tipo, anio);
    }

    private DocumentoMunicipal crear(DocumentoMunicipalRequest request, TipoDocumentoMunicipal tipo) {
        DocumentoMunicipal documento = new DocumentoMunicipal();
        documento.setTitulo(request.titulo());
        documento.setFecha(request.fecha());
        documento.setHora(request.hora());
        documento.setAnio(request.anio());
        documento.setDescripcion(request.descripcion());
        documento.setArchivoUrl(request.archivoUrl());
        documento.setTipo(tipo);

        return documentoRepository.save(documento);
    }

    private void eliminar(Long id, TipoDocumentoMunicipal tipo) {
        DocumentoMunicipal documento = documentoRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Documento no encontrado"));

        if (documento.getTipo() != tipo) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Documento no encontrado");
        }

        documentoRepository.delete(documento);
    }
}
