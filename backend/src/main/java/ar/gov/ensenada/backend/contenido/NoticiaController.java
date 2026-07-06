package ar.gov.ensenada.backend.contenido;

import jakarta.validation.Valid;
import org.springframework.http.HttpStatus;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import java.util.List;

@RestController
@RequestMapping("/api")
public class NoticiaController {

    private final NoticiaRepository noticiaRepository;

    public NoticiaController(NoticiaRepository noticiaRepository) {
        this.noticiaRepository = noticiaRepository;
    }

    @GetMapping("/noticias")
    public List<Noticia> listarPublicadas() {
        return noticiaRepository.findByEstadoOrderByIdDesc(EstadoPublicacion.Publicada);
    }

    @GetMapping("/noticias/destacadas")
    public List<Noticia> listarDestacadas() {
        return noticiaRepository.findByEstadoAndDestacadaTrueOrderByIdDesc(EstadoPublicacion.Publicada);
    }

    @GetMapping("/noticias/{id}")
    public Noticia obtenerPublica(@PathVariable Long id) {
        Noticia noticia = noticiaRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada"));

        if (noticia.getEstado() != EstadoPublicacion.Publicada) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada");
        }

        return noticia;
    }

    @GetMapping("/admin/noticias")
    public List<Noticia> listarAdmin() {
        return noticiaRepository.findAllByOrderByIdDesc();
    }

    @GetMapping("/admin/noticias/{id}")
    public Noticia obtenerAdmin(@PathVariable Long id) {
        return noticiaRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada"));
    }

    @PostMapping("/admin/noticias")
    @ResponseStatus(HttpStatus.CREATED)
    public Noticia crear(@Valid @RequestBody NoticiaRequest request) {
        Noticia noticia = new Noticia();
        completar(noticia, request);
        return noticiaRepository.save(noticia);
    }

    @PutMapping("/admin/noticias/{id}")
    public Noticia actualizar(@PathVariable Long id, @Valid @RequestBody NoticiaRequest request) {
        Noticia noticia = noticiaRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada"));

        completar(noticia, request);
        return noticiaRepository.save(noticia);
    }

    @DeleteMapping("/admin/noticias/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    public void eliminar(@PathVariable Long id) {
        if (!noticiaRepository.existsById(id)) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada");
        }

        noticiaRepository.deleteById(id);
    }

    private void completar(Noticia noticia, NoticiaRequest request) {
        noticia.setTitulo(request.titulo());
        noticia.setBajada(request.bajada());
        noticia.setContenido(request.contenido());
        noticia.setImagen(request.imagen());
        noticia.setCategoria(request.categoria());
        noticia.setFecha(request.fecha());
        noticia.setEstado(request.estado());
        noticia.setDestacada(request.destacada());
    }
}
