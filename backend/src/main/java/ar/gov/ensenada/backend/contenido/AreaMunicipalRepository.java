package ar.gov.ensenada.backend.contenido;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface AreaMunicipalRepository extends JpaRepository<AreaMunicipal, Long> {
    List<AreaMunicipal> findAllByOrderByIdAsc();

    boolean existsByNombreIgnoreCase(String nombre);
}
