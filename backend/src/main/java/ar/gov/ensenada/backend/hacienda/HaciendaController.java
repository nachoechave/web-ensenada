package ar.gov.ensenada.backend.hacienda;

import jakarta.validation.Valid;
import jakarta.validation.constraints.*;
import org.springframework.core.io.Resource;
import org.springframework.http.*;
import org.springframework.web.bind.annotation.*;
import org.springframework.web.multipart.MultipartFile;
import java.nio.charset.StandardCharsets;
import java.util.List;

@RestController
public class HaciendaController {
    private final HaciendaService service;
    private final HaciendaStorage storage;
    public HaciendaController(HaciendaService service,HaciendaStorage storage){this.service=service;this.storage=storage;}
    @GetMapping("/api/hacienda") public List<PublicacionHaciendaResponse> listar(){return service.listar(true);}
    @GetMapping("/api/hacienda/{id}") public PublicacionHaciendaResponse obtener(@PathVariable Long id){return service.obtener(id,true);}
    @GetMapping("/api/admin/hacienda") public List<PublicacionHaciendaResponse> listarAdmin(){return service.listar(false);}
    @GetMapping("/api/admin/hacienda/{id}") public PublicacionHaciendaResponse obtenerAdmin(@PathVariable Long id){return service.obtener(id,false);}
    @PostMapping("/api/admin/hacienda") @ResponseStatus(HttpStatus.CREATED)
    public PublicacionHaciendaResponse crear(@Valid @RequestBody PublicacionHaciendaRequest r){return service.guardar(null,r);}
    @PutMapping("/api/admin/hacienda/{id}")
    public PublicacionHaciendaResponse editar(@PathVariable Long id,@Valid @RequestBody PublicacionHaciendaRequest r){return service.guardar(id,r);}
    @DeleteMapping("/api/admin/hacienda/{id}") @ResponseStatus(HttpStatus.NO_CONTENT)
    public void archivar(@PathVariable Long id){service.archivar(id);}
    @PostMapping(value="/api/admin/hacienda/{id}/archivos",consumes=MediaType.MULTIPART_FORM_DATA_VALUE)
    @ResponseStatus(HttpStatus.CREATED)
    public PublicacionHaciendaResponse adjuntar(@PathVariable Long id,@RequestParam("archivo") MultipartFile file){return service.adjuntar(id,file);}
    @DeleteMapping("/api/admin/hacienda/{id}/archivos/{archivoId}")
    public PublicacionHaciendaResponse quitar(@PathVariable Long id,@PathVariable Long archivoId){return service.quitar(id,archivoId);}
    public record OrdenRequest(@NotNull @Size(max=50) List<@NotNull Long> ids){}
    @PutMapping("/api/admin/hacienda/{id}/archivos/orden")
    public PublicacionHaciendaResponse ordenar(@PathVariable Long id,@Valid @RequestBody OrdenRequest r){return service.ordenar(id,r.ids());}
    @GetMapping("/uploads/hacienda/{nombre}")
    public ResponseEntity<Resource> archivoPublico(@PathVariable String nombre,@RequestParam(defaultValue="false") boolean descargar){return contenido(service.archivoPublico(nombre),descargar);}
    @GetMapping("/api/admin/hacienda/{id}/archivos/{archivoId}/contenido")
    public ResponseEntity<Resource> archivoAdmin(@PathVariable Long id,@PathVariable Long archivoId){return contenido(service.archivoAdmin(id,archivoId),true);}
    private ResponseEntity<Resource> contenido(ArchivoHacienda a,boolean descargar){
        return ResponseEntity.ok().contentType(MediaType.parseMediaType(a.getTipoMime()))
            .contentLength(a.getTamanio()).cacheControl(CacheControl.noStore())
            .header("X-Content-Type-Options","nosniff")
            .header("Content-Security-Policy","sandbox; default-src 'none'")
            .header(HttpHeaders.CONTENT_DISPOSITION,(descargar?ContentDisposition.attachment():ContentDisposition.inline()).filename(a.getNombreAlmacenado(),StandardCharsets.UTF_8).build().toString())
            .body(storage.leer(a.getNombreAlmacenado()));
    }
}
