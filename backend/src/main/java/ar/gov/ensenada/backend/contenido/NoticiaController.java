package ar.gov.ensenada.backend.contenido;

import ar.gov.ensenada.backend.auth.Usuario;
import ar.gov.ensenada.backend.auth.UsuarioRepository;
import jakarta.transaction.Transactional;
import jakarta.validation.Valid;
import org.springframework.data.domain.Page;
import org.springframework.data.domain.PageRequest;
import org.springframework.data.domain.Sort;
import org.springframework.http.HttpStatus;
import org.springframework.security.core.context.SecurityContextHolder;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.server.ResponseStatusException;

import java.text.Normalizer;
import java.time.Instant;
import java.util.List;
import java.util.ArrayList;
import java.util.LinkedHashSet;
import java.util.Locale;

@RestController
@RequestMapping("/api")
public class NoticiaController {

    private final NoticiaRepository noticiaRepository;
    private final AuditoriaNoticiaRepository auditoriaRepository;
    private final UsuarioRepository usuarioRepository;

    public NoticiaController(
            NoticiaRepository noticiaRepository,
            AuditoriaNoticiaRepository auditoriaRepository,
            UsuarioRepository usuarioRepository
    ) {
        this.noticiaRepository = noticiaRepository;
        this.auditoriaRepository = auditoriaRepository;
        this.usuarioRepository = usuarioRepository;
    }

    @GetMapping("/noticias")
    public PaginaNoticias listarPublicadas(
            @RequestParam(defaultValue = "0") int page,
            @RequestParam(defaultValue = "12") int size
    ) {
        int pagina = Math.max(0, page);
        int tamanio = Math.min(48, Math.max(1, size));
        Page<Noticia> resultado = noticiaRepository.findByEstado(
                EstadoPublicacion.PUBLICADA,
                PageRequest.of(pagina, tamanio, Sort.by(Sort.Direction.DESC, "id"))
        );

        return new PaginaNoticias(
                resultado.getContent(),
                resultado.getNumber(),
                resultado.getSize(),
                resultado.getTotalElements(),
                resultado.getTotalPages(),
                resultado.isFirst(),
                resultado.isLast()
        );
    }

    @GetMapping("/noticias/destacadas")
    public List<Noticia> listarDestacadas() {
        return noticiaRepository.findByEstadoAndDestacadaTrueOrderByIdDesc(EstadoPublicacion.PUBLICADA);
    }

    @GetMapping("/noticias/{identificador}")
    public Noticia obtenerPublica(@PathVariable String identificador) {
        Noticia noticia = buscarPublica(identificador);

        return noticia;
    }

    @GetMapping("/admin/noticias")
    public List<Noticia> listarAdmin() {
        return noticiaRepository.findAllByOrderByIdDesc();
    }

    @GetMapping("/admin/noticias/auditoria")
    public List<AuditoriaNoticia> listarAuditoria() {
        return auditoriaRepository.findTop100ByOrderByFechaDesc();
    }

    @GetMapping("/admin/noticias/{id}")
    public Noticia obtenerAdmin(@PathVariable Long id) {
        return noticiaRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada"));
    }

    @PostMapping("/admin/noticias")
    @ResponseStatus(HttpStatus.CREATED)
    @Transactional
    public Noticia crear(@Valid @RequestBody NoticiaRequest request) {
        Noticia noticia = new Noticia();
        noticia.setSlug(generarSlugUnico(request.titulo()));
        completar(noticia, request);
        Noticia guardada = noticiaRepository.save(noticia);
        registrarAuditoria(guardada, "CREACION");
        return guardada;
    }

    @PutMapping("/admin/noticias/{id}")
    @Transactional
    public Noticia actualizar(@PathVariable Long id, @Valid @RequestBody NoticiaRequest request) {
        Noticia noticia = noticiaRepository.findById(id)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada"));

        EstadoPublicacion estadoAnterior = noticia.getEstado();
        completar(noticia, request);
        Noticia guardada = noticiaRepository.save(noticia);
        String accion = "EDICION";
        if (estadoAnterior != request.estado()) {
            accion = request.estado() == EstadoPublicacion.PUBLICADA ? "PUBLICACION" : "DESPUBLICACION";
        }
        registrarAuditoria(guardada, accion);
        return guardada;
    }

    @DeleteMapping("/admin/noticias/{id}")
    @ResponseStatus(HttpStatus.NO_CONTENT)
    @Transactional
    public void eliminar(@PathVariable Long id) {
        Noticia noticia = obtenerAdmin(id);
        noticia.setEstado(EstadoPublicacion.ARCHIVADA);
        Noticia guardada = noticiaRepository.save(noticia);
        registrarAuditoria(guardada, "ARCHIVADO");
    }

    private Noticia buscarPublica(String identificador) {
        Noticia noticia;
        if (identificador.matches("\\d+")) {
            try {
                noticia = noticiaRepository.findById(Long.parseLong(identificador))
                        .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada"));
            } catch (NumberFormatException ex) {
                throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada");
            }
        } else {
            noticia = noticiaRepository.findBySlug(identificador)
                    .orElseThrow(() -> new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada"));
        }

        if (noticia.getEstado() != EstadoPublicacion.PUBLICADA) {
            throw new ResponseStatusException(HttpStatus.NOT_FOUND, "Noticia no encontrada");
        }
        return noticia;
    }

    private String generarSlugUnico(String titulo) {
        String base = Normalizer.normalize(titulo, Normalizer.Form.NFD)
                .replaceAll("\\p{M}", "")
                .toLowerCase(Locale.ROOT)
                .replaceAll("[^a-z0-9]+", "-")
                .replaceAll("^-+|-+$", "");

        if (base.isBlank() || base.matches("\\d+")) base = "noticia-" + base;
        if (base.length() > 250) base = base.substring(0, 250).replaceAll("-+$", "");

        String candidato = base;
        int sufijo = 2;
        while (noticiaRepository.existsBySlug(candidato)) {
            candidato = base + "-" + sufijo++;
        }
        return candidato;
    }

    private void registrarAuditoria(Noticia noticia, String accion) {
        String email = SecurityContextHolder.getContext().getAuthentication().getName();
        Usuario usuario = usuarioRepository.findByEmailIgnoreCase(email)
                .orElseThrow(() -> new ResponseStatusException(HttpStatus.UNAUTHORIZED, "Usuario no encontrado"));

        AuditoriaNoticia auditoria = new AuditoriaNoticia();
        auditoria.setNoticiaId(noticia.getId());
        auditoria.setNoticiaTitulo(noticia.getTitulo());
        auditoria.setAccion(accion);
        auditoria.setEstadoResultante(noticia.getEstado().name());
        auditoria.setUsuarioNombre(usuario.getNombre());
        auditoria.setUsuarioEmail(usuario.getEmail());
        auditoria.setFecha(Instant.now());
        auditoriaRepository.save(auditoria);
    }

    public record PaginaNoticias(
            List<Noticia> content,
            int page,
            int size,
            long totalElements,
            int totalPages,
            boolean first,
            boolean last
    ) {}

    private void completar(Noticia noticia, NoticiaRequest request) {
        noticia.setTitulo(request.titulo());
        noticia.setBajada(request.bajada());
        noticia.setContenido(request.contenido());
        List<String> imagenes = request.imagenes() == null || request.imagenes().isEmpty()
                ? new ArrayList<>(List.of(request.imagen()))
                : new ArrayList<>(new LinkedHashSet<>(request.imagenes()));
        if (imagenes.size() > 6 || !imagenes.contains(request.imagen())) {
            throw new ResponseStatusException(HttpStatus.BAD_REQUEST, "La portada debe pertenecer a una galeria de hasta 6 imagenes");
        }
        noticia.setImagen(request.imagen());
        noticia.setImagenes(imagenes);
        noticia.setCategoria(request.categoria());
        noticia.setFechaPublicacion(request.fechaPublicacion());
        noticia.setEstado(request.estado());
        noticia.setDestacada(request.destacada());
    }
}
