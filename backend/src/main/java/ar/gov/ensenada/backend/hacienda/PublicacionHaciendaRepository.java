package ar.gov.ensenada.backend.hacienda;
import ar.gov.ensenada.backend.contenido.EstadoPublicacion;
import org.springframework.data.jpa.repository.*;
import jakarta.persistence.LockModeType;
import java.util.*;

public interface PublicacionHaciendaRepository extends JpaRepository<PublicacionHacienda,Long> {
    List<PublicacionHacienda> findAllByOrderByFechaPublicacionDescIdDesc();
    List<PublicacionHacienda> findByEstadoOrderByFechaPublicacionDescIdDesc(EstadoPublicacion estado);
    @Lock(LockModeType.PESSIMISTIC_WRITE)
    @Query("select p from PublicacionHacienda p where p.id = :id")
    Optional<PublicacionHacienda> bloquear(Long id);
}
