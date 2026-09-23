package ar.gov.ensenada.backend;

import ar.gov.ensenada.backend.auth.*;
import ar.gov.ensenada.backend.contenido.*;
import com.jayway.jsonpath.JsonPath;
import org.junit.jupiter.api.*;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.webmvc.test.autoconfigure.AutoConfigureMockMvc;
import org.springframework.http.MediaType;
import org.springframework.mock.web.MockMultipartFile;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;
import java.util.Set;
import java.io.ByteArrayOutputStream;
import java.awt.image.BufferedImage;
import javax.imageio.ImageIO;
import static org.junit.jupiter.api.Assertions.*;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;
import static org.springframework.security.test.web.servlet.request.SecurityMockMvcRequestPostProcessors.jwt;

@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class PortalIntegrationTests {
    @Autowired MockMvc mvc;
    @Autowired UsuarioRepository usuarios;
    @Autowired NoticiaRepository noticias;
    @Autowired PasswordEncoder encoder;
    String prensa, admin;
    Long periodistaId;
    final String password = "Test-password-1234";

    @BeforeEach void setup() throws Exception {
        noticias.deleteAll(); usuarios.deleteAll();
        periodistaId = usuarios.save(new Usuario("Prensa", "prensa@example.test", encoder.encode(password), Set.of(RolUsuario.PRENSA), true)).getId();
        usuarios.save(new Usuario("Admin", "admin@example.test", encoder.encode(password), Set.of(RolUsuario.SUPER_ADMIN), true));
        prensa = login("prensa@example.test"); admin = login("admin@example.test");
    }
    String login(String email) throws Exception {
        String response = mvc.perform(post("/api/auth/login").contentType(MediaType.APPLICATION_JSON)
            .content("{\"email\":\"" + email + "\",\"password\":\"" + password + "\"}"))
            .andExpect(status().isOk()).andExpect(jsonPath("$.password").doesNotExist()).andReturn().getResponse().getContentAsString();
        return JsonPath.read(response, "$.token");
    }
    String noticia(String estado, boolean destacada) {
        return """
            {"titulo":"Nueva noticia","bajada":"Resumen","contenido":"Contenido completo",
             "imagen":"/assets/ensenada-hero.jpg","categoria":"Institucional",
             "fechaPublicacion":"2026-09-22","estado":"%s","destacada":%s}
            """.formatted(estado,destacada);
    }
    long crear(String estado, boolean destacada) throws Exception {
        String body = mvc.perform(post("/api/admin/noticias").header("Authorization","Bearer " + prensa)
            .contentType(MediaType.APPLICATION_JSON).content(noticia(estado,destacada)))
            .andExpect(status().isCreated()).andReturn().getResponse().getContentAsString();
        return ((Number)JsonPath.read(body,"$.id")).longValue();
    }
    @Test void loginCorrectoYPerfilSinHash() throws Exception {
        mvc.perform(get("/api/auth/me").header("Authorization","Bearer " + prensa))
            .andExpect(status().isOk()).andExpect(jsonPath("$.roles[0]").value("PRENSA"))
            .andExpect(jsonPath("$.password").doesNotExist());
    }
    @Test void loginIncorrecto() throws Exception {
        mvc.perform(post("/api/auth/login").contentType(MediaType.APPLICATION_JSON)
            .content("{\"email\":\"prensa@example.test\",\"password\":\"incorrecta\"}"))
            .andExpect(status().isUnauthorized());
    }
    @Test void sinTokenEs401() throws Exception { mvc.perform(get("/api/admin/noticias")).andExpect(status().isUnauthorized()); }
    @Test void prensaNoGestionaUsuarios() throws Exception {
        mvc.perform(get("/api/admin/usuarios").header("Authorization","Bearer " + prensa)).andExpect(status().isForbidden());
        mvc.perform(post("/api/admin/usuarios").header("Authorization","Bearer " + prensa).contentType(MediaType.APPLICATION_JSON).content("{}"))
            .andExpect(status().isForbidden());
    }
    @Test void superAdminGestionaUsuariosSinHashes() throws Exception {
        mvc.perform(get("/api/admin/usuarios").header("Authorization","Bearer " + admin))
            .andExpect(status().isOk()).andExpect(jsonPath("$[0].password").doesNotExist());
        mvc.perform(post("/api/admin/usuarios").header("Authorization","Bearer " + admin).contentType(MediaType.APPLICATION_JSON)
            .content("{\"nombre\":\"Nueva\",\"email\":\"nueva@example.test\",\"password\":\"Test-password-5678\",\"roles\":[\"PRENSA\"],\"activo\":true}"))
            .andExpect(status().isCreated()).andExpect(jsonPath("$.password").doesNotExist());
    }
    @Test void desactivarRevocaTokenExistente() throws Exception {
        mvc.perform(put("/api/admin/usuarios/"+periodistaId+"/activo").header("Authorization","Bearer " + admin)
            .contentType(MediaType.APPLICATION_JSON).content("{\"activo\":false}")).andExpect(status().isOk());
        mvc.perform(get("/api/admin/noticias").header("Authorization","Bearer " + prensa)).andExpect(status().isUnauthorized());
    }
    @Test void noPermiteEscalarRolNiDesactivarSuperAdmin() throws Exception {
        mvc.perform(put("/api/admin/usuarios/"+periodistaId+"/roles").header("Authorization","Bearer " + admin)
            .contentType(MediaType.APPLICATION_JSON).content("{\"roles\":[\"SUPER_ADMIN\"]}")).andExpect(status().isBadRequest());
        Long id = usuarios.findByEmailIgnoreCase("admin@example.test").orElseThrow().getId();
        mvc.perform(put("/api/admin/usuarios/"+id+"/activo").header("Authorization","Bearer " + admin)
            .contentType(MediaType.APPLICATION_JSON).content("{\"activo\":false}")).andExpect(status().isForbidden());
    }
    @Test void crearEditarPublicarYArchivar() throws Exception {
        long id=crear("BORRADOR",true);
        mvc.perform(get("/api/noticias")).andExpect(jsonPath("$.length()").value(0));
        mvc.perform(get("/api/noticias/"+id)).andExpect(status().isNotFound());
        mvc.perform(put("/api/admin/noticias/"+id).header("Authorization","Bearer "+prensa)
            .contentType(MediaType.APPLICATION_JSON).content(noticia("PUBLICADA",true).replace("Nueva noticia","Título editado")))
            .andExpect(status().isOk()).andExpect(jsonPath("$.titulo").value("Título editado"));
        mvc.perform(get("/api/noticias")).andExpect(jsonPath("$.length()").value(1));
        mvc.perform(get("/api/noticias/"+id)).andExpect(status().isOk());
        mvc.perform(delete("/api/admin/noticias/"+id).header("Authorization","Bearer "+prensa)).andExpect(status().isNoContent());
        assertEquals(EstadoPublicacion.ARCHIVADA, noticias.findById(id).orElseThrow().getEstado());
        mvc.perform(get("/api/noticias")).andExpect(jsonPath("$.length()").value(0));
        mvc.perform(get("/api/noticias/"+id)).andExpect(status().isNotFound());
        mvc.perform(get("/api/admin/noticias/"+id).header("Authorization","Bearer "+prensa))
            .andExpect(status().isOk()).andExpect(jsonPath("$.estado").value("ARCHIVADA"));
    }
    @Test void destacadasSoloPublicadasYMarcadas() throws Exception {
        crear("BORRADOR",true); crear("ARCHIVADA",true); crear("PUBLICADA",false); long id=crear("PUBLICADA",true);
        mvc.perform(get("/api/noticias/destacadas")).andExpect(jsonPath("$.length()").value(1)).andExpect(jsonPath("$[0].id").value(id));
        mvc.perform(get("/api/noticias")).andExpect(jsonPath("$.length()").value(2));
    }
    @Test void validacionDeNoticia() throws Exception {
        mvc.perform(post("/api/admin/noticias").header("Authorization","Bearer "+prensa).contentType(MediaType.APPLICATION_JSON)
            .content(noticia("PUBLICADA",false).replace("Nueva noticia"," "))).andExpect(status().isBadRequest());
    }
    @Test void uploadRechazaSvgAunqueMimeDigaPng() throws Exception {
        mvc.perform(multipart("/api/admin/archivos/noticias").file(new MockMultipartFile("archivo","x.png","image/png","<svg onload='alert(1)'/>".getBytes()))
            .header("Authorization","Bearer "+prensa)).andExpect(status().isBadRequest());
    }
    @Test void uploadSinTokenYSinRol() throws Exception {
        MockMultipartFile file=new MockMultipartFile("archivo","x.png","image/png",new byte[]{1,2,3});
        mvc.perform(multipart("/api/admin/archivos/noticias").file(file)).andExpect(status().isUnauthorized());
        mvc.perform(multipart("/api/admin/archivos/noticias").file(file).with(jwt().authorities(new org.springframework.security.core.authority.SimpleGrantedAuthority("ROLE_OTRO"))))
            .andExpect(status().isForbidden());
    }
    @Test void uploadGeneraNombreYFormatoReal() throws Exception {
        ByteArrayOutputStream bytes=new ByteArrayOutputStream(); ImageIO.write(new BufferedImage(2,2,BufferedImage.TYPE_INT_RGB),"png",bytes);
        mvc.perform(multipart("/api/admin/archivos/noticias").file(new MockMultipartFile("archivo","../../ataque.svg","text/plain",bytes.toByteArray()))
            .header("Authorization","Bearer "+prensa)).andExpect(status().isOk())
            .andExpect(jsonPath("$.contentType").value("image/png"))
            .andExpect(jsonPath("$.url").value(org.hamcrest.Matchers.matchesPattern("/uploads/noticias/[a-f0-9-]+\\.png")));
    }
}
