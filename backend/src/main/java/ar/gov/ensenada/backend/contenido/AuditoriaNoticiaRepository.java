package ar.gov.ensenada.backend.contenido;

import org.springframework.data.jpa.repository.JpaRepository;
import java.util.List;

public interface AuditoriaNoticiaRepository extends JpaRepository<AuditoriaNoticia, Long> {
    List<AuditoriaNoticia> findTop100ByOrderByFechaDesc();
}
