package ar.gov.ensenada.backend.hacienda;

import ar.gov.ensenada.backend.contenido.EstadoPublicacion;
import org.springframework.stereotype.Service;
import org.springframework.transaction.annotation.Transactional;
import org.springframework.http.HttpStatus;
import org.springframework.web.server.ResponseStatusException;
import org.springframework.web.multipart.MultipartFile;
import java.util.*;

@Service
@Transactional
public class HaciendaService {
    private final PublicacionHaciendaRepository publicaciones;
    private final ArchivoHaciendaRepository archivos;
    private final HaciendaStorage storage;
    public HaciendaService(PublicacionHaciendaRepository publicaciones,ArchivoHaciendaRepository archivos,HaciendaStorage storage){this.publicaciones=publicaciones;this.archivos=archivos;this.storage=storage;}
    @Transactional(readOnly=true)
    public List<PublicacionHaciendaResponse> listar(boolean publico){
        var rows=publico?publicaciones.findByEstadoOrderByFechaPublicacionDescIdDesc(EstadoPublicacion.PUBLICADA):publicaciones.findAllByOrderByFechaPublicacionDescIdDesc();
        return rows.stream().map(PublicacionHaciendaResponse::from).toList();
    }
    @Transactional(readOnly=true)
    public PublicacionHaciendaResponse obtener(Long id,boolean publico){
        var p=publicaciones.findById(id).orElseThrow(this::noEncontrada);
        if(publico && p.getEstado()!=EstadoPublicacion.PUBLICADA) throw noEncontrada();
        return PublicacionHaciendaResponse.from(p);
    }
    public PublicacionHaciendaResponse guardar(Long id,PublicacionHaciendaRequest request){
        var p=id==null?new PublicacionHacienda():bloquear(id);
        if(request.estado()==EstadoPublicacion.PUBLICADA && p.getArchivos().isEmpty()) throw new ResponseStatusException(HttpStatus.BAD_REQUEST,"Adjuntá al menos un documento antes de publicar");
        p.actualizar(request);
        return PublicacionHaciendaResponse.from(publicaciones.save(p));
    }
    public void archivar(Long id){bloquear(id).setEstado(EstadoPublicacion.ARCHIVADA);}
    public PublicacionHaciendaResponse adjuntar(Long id,MultipartFile file){
        var p=bloquear(id);
        if(p.getArchivos().size()>=50) throw new ResponseStatusException(HttpStatus.BAD_REQUEST,"Máximo 50 documentos por publicación");
        var stored=storage.guardar(file);
        var a=new ArchivoHacienda(p,stored.original(),stored.nombre(),stored.mime(),p.getArchivos().size(),stored.tamanio());
        p.getArchivos().add(a); archivos.save(a);
        return PublicacionHaciendaResponse.from(p);
    }
    public PublicacionHaciendaResponse quitar(Long id,Long archivoId){
        var p=bloquear(id);
        var a=p.getArchivos().stream().filter(x->x.getId().equals(archivoId)).findFirst().orElseThrow(this::noEncontrada);
        if(p.getEstado()==EstadoPublicacion.PUBLICADA && p.getArchivos().size()==1) throw new ResponseStatusException(HttpStatus.BAD_REQUEST,"Despublicá antes de retirar el último documento");
        p.getArchivos().remove(a);
        for(int i=0;i<p.getArchivos().size();i++) p.getArchivos().get(i).setOrden(i);
        // Physical data retained for backup/maintenance; removed metadata makes its URL inaccessible.
        return PublicacionHaciendaResponse.from(p);
    }
    public PublicacionHaciendaResponse ordenar(Long id,List<Long> ids){
        var p=bloquear(id);
        Set<Long> current=new HashSet<>();p.getArchivos().forEach(a->current.add(a.getId()));
        if(ids.size()!=current.size() || !new HashSet<>(ids).equals(current)) throw new ResponseStatusException(HttpStatus.BAD_REQUEST,"El orden debe incluir cada documento exactamente una vez");
        p.getArchivos().forEach(a->a.setOrden(ids.indexOf(a.getId())));
        return PublicacionHaciendaResponse.from(p);
    }
    @Transactional(readOnly=true)
    public ArchivoHacienda archivoPublico(String nombre){
        var a=archivos.findByNombreAlmacenado(nombre).orElseThrow(this::noEncontrada);
        if(a.getPublicacion().getEstado()!=EstadoPublicacion.PUBLICADA) throw noEncontrada();
        return a;
    }
    @Transactional(readOnly=true)
    public ArchivoHacienda archivoAdmin(Long id,Long archivoId){
        var a=archivos.findById(archivoId).orElseThrow(this::noEncontrada);
        if(!a.getPublicacion().getId().equals(id)) throw noEncontrada();
        return a;
    }
    private PublicacionHacienda bloquear(Long id){return publicaciones.bloquear(id).orElseThrow(this::noEncontrada);}
    private ResponseStatusException noEncontrada(){return new ResponseStatusException(HttpStatus.NOT_FOUND,"Publicación o documento no encontrado");}
}
