package ar.gov.ensenada.backend.hacienda;
import ar.gov.ensenada.backend.contenido.EstadoPublicacion;
import java.time.LocalDate;
import java.util.List;

public record PublicacionHaciendaResponse(Long id, String titulo, String descripcion,
    TipoPublicacionHacienda tipo, LocalDate fechaPublicacion, EstadoPublicacion estado,
    List<ArchivoResponse> archivos) {
    public record ArchivoResponse(Long id, String nombreOriginal, String url, String tipoMime, int orden, long tamanio) {
        static ArchivoResponse from(ArchivoHacienda a){
            return new ArchivoResponse(a.getId(),a.getNombreOriginal(),"/uploads/hacienda/"+a.getNombreAlmacenado(),a.getTipoMime(),a.getOrden(),a.getTamanio());
        }
    }
    static PublicacionHaciendaResponse from(PublicacionHacienda p){
        return new PublicacionHaciendaResponse(p.getId(),p.getTitulo(),p.getDescripcion(),p.getTipo(),p.getFechaPublicacion(),p.getEstado(),
            p.getArchivos().stream().sorted(java.util.Comparator.comparingInt(ArchivoHacienda::getOrden)).map(ArchivoResponse::from).toList());
    }
}
