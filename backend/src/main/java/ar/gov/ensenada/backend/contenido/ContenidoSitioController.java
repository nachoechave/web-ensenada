package ar.gov.ensenada.backend.contenido;

import jakarta.validation.Valid;
import org.springframework.web.bind.annotation.GetMapping;
import org.springframework.web.bind.annotation.PutMapping;
import org.springframework.web.bind.annotation.RequestBody;
import org.springframework.web.bind.annotation.RequestMapping;
import org.springframework.web.bind.annotation.RestController;

@RestController
@RequestMapping("/api")
public class ContenidoSitioController {

    private static final Long CONTENIDO_ID = 1L;

    private static final String HERO_TITULO = "Construimos juntos la ciudad que sonamos";
    private static final String HERO_BAJADA = "Informacion, servicios y novedades para estar cerca de cada vecino y vecina de Ensenada.";
    private static final String FOOTER_DESCRIPCION = "Un municipio cerca de cada vecino, trabajando por una ciudad mas integrada, moderna y participativa.";
    private static final String EMAIL_CONTACTO = "info@ensenada.gob.ar";
    private static final String TELEFONO_CONTACTO = "0221 460-0000";
    private static final String DIRECCION_MUNICIPIO = "Ensenada, Buenos Aires";
    private static final String HORARIO_ATENCION = "Lunes a viernes de 8 a 14 hs";
    private static final String TEXTO_TRAMITES = "Orientacion sobre servicios, turnos y solicitudes municipales.";
    private static final String NOTA_INSTITUCIONAL_CONTACTO = "Esta web reune informacion dinamica de noticias, agenda, servicios y canales de contacto municipales.";

    private final ContenidoSitioRepository contenidoRepository;

    public ContenidoSitioController(ContenidoSitioRepository contenidoRepository) {
        this.contenidoRepository = contenidoRepository;
    }

    @GetMapping("/contenido-sitio")
    public ContenidoSitio obtener() {
        ContenidoSitio contenido = contenidoRepository.findById(CONTENIDO_ID)
                .orElseGet(this::crearContenidoInicial);

        return completarValoresSiFaltan(contenido);
    }

    @PutMapping("/admin/contenido")
    public ContenidoSitio actualizar(@Valid @RequestBody ContenidoSitioRequest request) {
        ContenidoSitio contenido = contenidoRepository.findById(CONTENIDO_ID)
                .orElseGet(this::crearContenidoInicial);

        contenido.setHeroTitulo(request.heroTitulo());
        contenido.setHeroBajada(request.heroBajada());
        contenido.setFooterDescripcion(request.footerDescripcion());
        contenido.setEmailContacto(request.emailContacto());
        contenido.setTelefonoContacto(request.telefonoContacto());
        contenido.setDireccionMunicipio(request.direccionMunicipio());
        contenido.setHorarioAtencion(request.horarioAtencion());
        contenido.setTextoTramites(request.textoTramites());
        contenido.setNotaInstitucionalContacto(request.notaInstitucionalContacto());

        return contenidoRepository.save(contenido);
    }

    private ContenidoSitio crearContenidoInicial() {
        ContenidoSitio contenido = new ContenidoSitio();
        contenido.setId(CONTENIDO_ID);
        contenido.setHeroTitulo(HERO_TITULO);
        contenido.setHeroBajada(HERO_BAJADA);
        contenido.setFooterDescripcion(FOOTER_DESCRIPCION);
        contenido.setEmailContacto(EMAIL_CONTACTO);
        contenido.setTelefonoContacto(TELEFONO_CONTACTO);
        contenido.setDireccionMunicipio(DIRECCION_MUNICIPIO);
        contenido.setHorarioAtencion(HORARIO_ATENCION);
        contenido.setTextoTramites(TEXTO_TRAMITES);
        contenido.setNotaInstitucionalContacto(NOTA_INSTITUCIONAL_CONTACTO);
        return contenidoRepository.save(contenido);
    }

    private ContenidoSitio completarValoresSiFaltan(ContenidoSitio contenido) {
        boolean actualizado = false;

        if (estaVacio(contenido.getTelefonoContacto())) {
            contenido.setTelefonoContacto(TELEFONO_CONTACTO);
            actualizado = true;
        }

        if (estaVacio(contenido.getDireccionMunicipio())) {
            contenido.setDireccionMunicipio(DIRECCION_MUNICIPIO);
            actualizado = true;
        }

        if (estaVacio(contenido.getHorarioAtencion())) {
            contenido.setHorarioAtencion(HORARIO_ATENCION);
            actualizado = true;
        }

        if (estaVacio(contenido.getTextoTramites())) {
            contenido.setTextoTramites(TEXTO_TRAMITES);
            actualizado = true;
        }

        if (estaVacio(contenido.getNotaInstitucionalContacto())) {
            contenido.setNotaInstitucionalContacto(NOTA_INSTITUCIONAL_CONTACTO);
            actualizado = true;
        }

        if (!actualizado) {
            return contenido;
        }

        return contenidoRepository.save(contenido);
    }

    private boolean estaVacio(String valor) {
        return valor == null || valor.isBlank();
    }
}
