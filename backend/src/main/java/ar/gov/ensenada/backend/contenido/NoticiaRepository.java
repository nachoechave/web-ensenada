package ar.gov.ensenada.backend.contenido;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface NoticiaRepository extends JpaRepository<Noticia, Long> {
    List<Noticia> findAllByOrderByIdDesc();
    List<Noticia> findByEstadoOrderByIdDesc(EstadoPublicacion estado);
    List<Noticia> findByEstadoAndDestacadaTrueOrderByIdDesc(EstadoPublicacion estado);
}
