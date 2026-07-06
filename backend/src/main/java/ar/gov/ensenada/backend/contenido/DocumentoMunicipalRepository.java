package ar.gov.ensenada.backend.contenido;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface DocumentoMunicipalRepository extends JpaRepository<DocumentoMunicipal, Long> {
    List<DocumentoMunicipal> findByTipoOrderByIdDesc(TipoDocumentoMunicipal tipo);
    List<DocumentoMunicipal> findByTipoAndAnioOrderByIdDesc(TipoDocumentoMunicipal tipo, Integer anio);
}
