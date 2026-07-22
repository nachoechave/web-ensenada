package ar.gov.ensenada.backend.contenido;

import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;

@RestController
@RequestMapping("/api")
public class AreaMunicipalController {

    private final AreaMunicipalRepository areaRepository;

    public AreaMunicipalController(AreaMunicipalRepository areaRepository) {
        this.areaRepository = areaRepository;
    }

    @GetMapping("/areas")
    public List<AreaMunicipal> listar() {
        return areaRepository.findAllByOrderByIdAsc();
    }

    @GetMapping("/admin/areas")
    public List<AreaMunicipal> listarAdmin() {
        return areaRepository.findAllByOrderByIdAsc();
    }

    @PostMapping("/admin/areas")
    @ResponseStatus(HttpStatus.CREATED)
    public AreaMunicipal crear(@Valid @RequestBody AreaMunicipalRequest request) {
        AreaMunicipal area = new AreaMunicipal();
        completar(area, request);
        return areaRepository.save(area);
    }

    @PutMapping("/admin/areas/{id}")
    public AreaMunicipal actualizar(
            @PathVariable Long id,
            @Valid @RequestBody AreaMunicipalRequest request
    ) {
        AreaMunicipal area = areaRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Area no encontrada"));

        completar(area, request);
        return areaRepository.save(area);
    }

    @DeleteMapping("/admin/areas/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void eliminar(@PathVariable Long id) {
        if (!areaRepository.existsById(id)) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Area no encontrada");
        }

        areaRepository.deleteById(id);
    }

    private void completar(AreaMunicipal area, AreaMunicipalRequest request) {
        area.setNombre(request.nombre());
        area.setDescripcion(request.descripcion());
        area.setTelefono(request.telefono());
        area.setEmail(normalizarOpcional(request.email()));
        area.setDireccion(request.direccion());
        area.setHorario(request.horario());
    }

    private String normalizarOpcional(String valor) {
        if (valor == null || valor.isBlank()) {
            return "";
        }

        return valor;
    }

}
