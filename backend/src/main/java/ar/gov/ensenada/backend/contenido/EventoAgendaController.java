package ar.gov.ensenada.backend.contenido;

import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;

@RestController
@RequestMapping("/api")
public class EventoAgendaController {

    private final EventoAgendaRepository eventoRepository;

    public EventoAgendaController(EventoAgendaRepository eventoRepository) {
        this.eventoRepository = eventoRepository;
    }

    @GetMapping("/agenda")
    public List<EventoAgenda> listar() {
        return eventoRepository.findAllByOrderByFechaAscIdAsc();
    }

    @GetMapping("/admin/agenda")
    public List<EventoAgenda> listarAdmin() {
        return eventoRepository.findAllByOrderByFechaAscIdAsc();
    }

    @PostMapping("/admin/agenda")
    @ResponseStatus(HttpStatus.CREATED)
    public EventoAgenda crear(@Valid @RequestBody EventoAgendaRequest request) {
        EventoAgenda evento = new EventoAgenda();
        completar(evento, request);
        return eventoRepository.save(evento);
    }

    @DeleteMapping("/admin/agenda/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void eliminar(@PathVariable Long id) {
        if (!eventoRepository.existsById(id)) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Evento no encontrado");
        }

        eventoRepository.deleteById(id);
    }

    private void completar(EventoAgenda evento, EventoAgendaRequest request) {
        evento.setFecha(request.fecha());
        evento.setHorario(request.horario());
        evento.setTitulo(request.titulo());
        evento.setLugar(request.lugar());
        evento.setCategoria(request.categoria());
        evento.setDescripcion(request.descripcion());
    }
}
