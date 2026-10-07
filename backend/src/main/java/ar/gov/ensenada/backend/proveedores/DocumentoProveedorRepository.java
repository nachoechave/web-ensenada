package ar.gov.ensenada.backend.proveedores;

import org.springframework.data.jpa.repository.JpaRepository;
import java.util.List;
import java.util.Optional;

public interface DocumentoProveedorRepository extends JpaRepository<DocumentoProveedor, Long> {
    List<DocumentoProveedor> findByActivoTrueOrderByOrdenAscIdAsc();
    List<DocumentoProveedor> findAllByOrderByOrdenAscIdAsc();
    Optional<DocumentoProveedor> findByNombreAlmacenado(String nombreAlmacenado);
}
