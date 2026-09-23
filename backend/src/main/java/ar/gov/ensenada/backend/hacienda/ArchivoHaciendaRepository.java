package ar.gov.ensenada.backend.hacienda;
import org.springframework.data.jpa.repository.JpaRepository;
import java.util.Optional;
public interface ArchivoHaciendaRepository extends JpaRepository<ArchivoHacienda,Long> {
    Optional<ArchivoHacienda> findByNombreAlmacenado(String nombre);
}
