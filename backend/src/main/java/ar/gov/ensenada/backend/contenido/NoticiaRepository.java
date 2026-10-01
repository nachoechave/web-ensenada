package ar.gov.ensenada.backend.contenido;

import org.springframework.data.domain.Page;
import org.springframework.data.domain.Pageable;
import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;
import java.util.Optional;

public interface NoticiaRepository extends JpaRepository<Noticia, Long> {
    Optional<Noticia> findBySlug(String slug);
    boolean existsBySlug(String slug);
    List<Noticia> findAllByOrderByIdDesc();
    Page<Noticia> findByEstado(EstadoPublicacion estado, Pageable pageable);
    List<Noticia> findByEstadoOrderByIdDesc(EstadoPublicacion estado);
    List<Noticia> findByEstadoAndDestacadaTrueOrderByIdDesc(EstadoPublicacion estado);
}
