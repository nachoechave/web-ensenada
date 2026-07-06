package ar.gov.ensenada.backend.contenido;

import org.springframework.data.jpa.repository.JpaRepository;

import java.util.List;

public interface EventoAgendaRepository extends JpaRepository<EventoAgenda, Long> {
    List<EventoAgenda> findAllByOrderByFechaAscIdAsc();
}
