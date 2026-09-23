package ar.gov.ensenada.backend;

import ar.gov.ensenada.backend.auth.*;
import ar.gov.ensenada.backend.hacienda.*;
import com.jayway.jsonpath.JsonPath;
import org.junit.jupiter.api.*;
import org.junit.jupiter.params.ParameterizedTest;
import org.junit.jupiter.params.provider.ValueSource;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.webmvc.test.autoconfigure.AutoConfigureMockMvc;
import org.springframework.http.MediaType;
import org.springframework.mock.web.MockMultipartFile;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;
import java.util.*;
import java.io.ByteArrayOutputStream;
import java.awt.image.BufferedImage;
import javax.imageio.ImageIO;
import static org.junit.jupiter.api.Assertions.*;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

@SpringBootTest @AutoConfigureMockMvc @ActiveProfiles("test")
class HaciendaIntegrationTests {
    @Autowired MockMvc mvc;
    @Autowired UsuarioRepository usuarios;
    @Autowired PublicacionHaciendaRepository publicaciones;
    @Autowired PasswordEncoder encoder;
    String hacienda, prensa, admin;
    static final byte[] PDF=pdfDePrueba();
    static byte[] pdfDePrueba(){
        StringBuilder pdf=new StringBuilder("%PDF-1.4\n");
        String[] objects={"<< /Type /Catalog /Pages 2 0 R >>","<< /Type /Pages /Kids [3 0 R] /Count 1 >>","<< /Type /Page /Parent 2 0 R /MediaBox [0 0 100 100] >>"};
        List<Integer> offsets=new ArrayList<>();
        for(int i=0;i<objects.length;i++){offsets.add(pdf.length());pdf.append(i+1).append(" 0 obj\n").append(objects[i]).append("\nendobj\n");}
        int xref=pdf.length();pdf.append("xref\n0 4\n0000000000 65535 f \n");
        offsets.forEach(offset->pdf.append(String.format(java.util.Locale.ROOT,"%010d 00000 n \n",offset)));
        pdf.append("trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n").append(xref).append("\n%%EOF\n");
        return pdf.toString().getBytes(java.nio.charset.StandardCharsets.US_ASCII);
    }
    @BeforeEach void setup() throws Exception {
        publicaciones.deleteAll();usuarios.deleteAll();
        for(var rol:RolUsuario.values())usuarios.save(new Usuario(rol.name(),rol.name()+"@example.test",encoder.encode("Test-password-1234"),Set.of(rol),true));
        hacienda=login("HACIENDA");prensa=login("PRENSA");admin=login("SUPER_ADMIN");
    }
    String login(String rol)throws Exception {
        String body=mvc.perform(post("/api/auth/login").contentType(MediaType.APPLICATION_JSON).content("{\"email\":\""+rol+"@example.test\",\"password\":\"Test-password-1234\"}"))
            .andExpect(status().isOk()).andReturn().getResponse().getContentAsString();return JsonPath.read(body,"$.token");
    }
    String body(String estado){return """
        {"titulo":"Presupuesto anual","descripcion":"Documentación financiera","tipo":"PRESUPUESTO","fechaPublicacion":"2026-09-22","estado":"%s"}
        """.formatted(estado);}
    long crear()throws Exception {
        String response=mvc.perform(post("/api/admin/hacienda").header("Authorization","Bearer "+hacienda).contentType(MediaType.APPLICATION_JSON).content(body("BORRADOR")))
            .andExpect(status().isCreated()).andReturn().getResponse().getContentAsString();return ((Number)JsonPath.read(response,"$.id")).longValue();
    }
    String adjuntar(long id,byte[] bytes,String nombre,String mime)throws Exception {
        return mvc.perform(multipart("/api/admin/hacienda/"+id+"/archivos").file(new MockMultipartFile("archivo",nombre,mime,bytes)).header("Authorization","Bearer "+hacienda))
            .andExpect(status().isCreated()).andReturn().getResponse().getContentAsString();
    }
    void estado(long id,String estado)throws Exception {
        mvc.perform(put("/api/admin/hacienda/"+id).header("Authorization","Bearer "+hacienda).contentType(MediaType.APPLICATION_JSON).content(body(estado)))
            .andExpect(status().isOk()).andExpect(jsonPath("$.estado").value(estado));
    }
    @Test void cicloEditorialYVisibilidad()throws Exception {
        long id=crear();String file=adjuntar(id,PDF,"presupuesto.pdf","application/pdf");String url=JsonPath.read(file,"$.archivos[0].url");
        mvc.perform(get("/api/admin/hacienda").header("Authorization","Bearer "+hacienda)).andExpect(status().isOk()).andExpect(jsonPath("$.length()").value(1));
        mvc.perform(get("/api/hacienda")).andExpect(status().isOk()).andExpect(jsonPath("$.length()").value(0));
        mvc.perform(get("/api/hacienda/"+id)).andExpect(status().isNotFound());mvc.perform(get(url)).andExpect(status().isNotFound());
        mvc.perform(put("/api/admin/hacienda/"+id).header("Authorization","Bearer "+hacienda).contentType(MediaType.APPLICATION_JSON).content(body("BORRADOR").replace("Presupuesto anual","Presupuesto editado")))
            .andExpect(status().isOk()).andExpect(jsonPath("$.titulo").value("Presupuesto editado"));
        estado(id,"PUBLICADA");mvc.perform(get("/api/hacienda")).andExpect(jsonPath("$.length()").value(1));
        mvc.perform(get("/api/hacienda/"+id)).andExpect(status().isOk()).andExpect(jsonPath("$.archivos.length()").value(1));
        mvc.perform(get(url)).andExpect(status().isOk()).andExpect(content().contentType("application/pdf")).andExpect(header().string("Cache-Control","no-store"));
        mvc.perform(get(url+"?descargar=true")).andExpect(status().isOk()).andExpect(header().string("Content-Disposition",org.hamcrest.Matchers.startsWith("attachment")));
        mvc.perform(delete("/api/admin/hacienda/"+id).header("Authorization","Bearer "+hacienda)).andExpect(status().isNoContent());
        assertTrue(publicaciones.existsById(id));mvc.perform(get("/api/hacienda")).andExpect(jsonPath("$.length()").value(0));
        mvc.perform(get("/api/hacienda/"+id)).andExpect(status().isNotFound());mvc.perform(get(url)).andExpect(status().isNotFound());
    }
    @Test void ordenYDesasociacionSonPersistentes()throws Exception {
        long id=crear();adjuntar(id,PDF,"primero.pdf","application/pdf");String result=adjuntar(id,PDF,"segundo.pdf","application/pdf");
        Number first=JsonPath.read(result,"$.archivos[0].id"),second=JsonPath.read(result,"$.archivos[1].id");
        mvc.perform(put("/api/admin/hacienda/"+id+"/archivos/orden").header("Authorization","Bearer "+hacienda).contentType(MediaType.APPLICATION_JSON).content("{\"ids\":["+second+","+first+"]}"))
            .andExpect(status().isOk()).andExpect(jsonPath("$.archivos[0].nombreOriginal").value("segundo.pdf"));
        mvc.perform(get("/api/admin/hacienda/"+id).header("Authorization","Bearer "+hacienda)).andExpect(jsonPath("$.archivos[0].id").value(second.intValue()));
        mvc.perform(put("/api/admin/hacienda/"+id+"/archivos/orden").header("Authorization","Bearer "+hacienda).contentType(MediaType.APPLICATION_JSON).content("{\"ids\":["+first+","+first+"]}"))
            .andExpect(status().isBadRequest());
        mvc.perform(delete("/api/admin/hacienda/"+id+"/archivos/"+second).header("Authorization","Bearer "+hacienda))
            .andExpect(status().isOk()).andExpect(jsonPath("$.archivos.length()").value(1)).andExpect(jsonPath("$.archivos[0].orden").value(0));
    }
    @ParameterizedTest @ValueSource(strings={"png","jpeg"})
    void aceptaImagenRealYRecodifica(String formato)throws Exception {
        long id=crear();ByteArrayOutputStream output=new ByteArrayOutputStream();ImageIO.write(new BufferedImage(3,3,BufferedImage.TYPE_INT_RGB),formato,output);
        String result=adjuntar(id,output.toByteArray(),"../../documento.svg","text/plain");
        assertEquals("image/"+formato,JsonPath.read(result,"$.archivos[0].tipoMime"));
        assertEquals("documento.svg",JsonPath.read(result,"$.archivos[0].nombreOriginal"));
        String url=JsonPath.read(result,"$.archivos[0].url");assertTrue(url.matches("/uploads/hacienda/[a-f0-9-]{36}\\.(jpg|png)"));
    }
    @Test void rechazaPdfFalsoYSvg()throws Exception {
        long id=crear();
        for(String payload:List.of("<html>falso.pdf</html>","<svg onload='alert(1)'/>","%PDF-1.4 incomplete"))
            mvc.perform(multipart("/api/admin/hacienda/"+id+"/archivos").file(new MockMultipartFile("archivo","x.pdf","application/pdf",payload.getBytes())).header("Authorization","Bearer "+hacienda)).andExpect(status().isBadRequest());
    }
    @Test void rechazaArchivosDemasiadoGrandes()throws Exception {
        long id=crear();
        for(int size:List.of(20*1024*1024+1,5*1024*1024+1))
            mvc.perform(multipart("/api/admin/hacienda/"+id+"/archivos").file(new MockMultipartFile("archivo","x.png","image/png",new byte[size])).header("Authorization","Bearer "+hacienda)).andExpect(status().isBadRequest());
    }
    @Test void permisosCruzadosYFailClosed()throws Exception {
        long id=crear();
        mvc.perform(get("/api/admin/hacienda").header("Authorization","Bearer "+prensa)).andExpect(status().isForbidden());
        mvc.perform(multipart("/api/admin/hacienda/"+id+"/archivos").file(new MockMultipartFile("archivo","x.pdf","application/pdf",PDF)).header("Authorization","Bearer "+prensa)).andExpect(status().isForbidden());
        for(String endpoint:List.of("noticias","usuarios","inexistente"))
            mvc.perform(get("/api/admin/"+endpoint).header("Authorization","Bearer "+hacienda)).andExpect(status().isForbidden());
        mvc.perform(multipart("/api/admin/archivos/noticias").file(new MockMultipartFile("archivo","x.png","image/png",new byte[]{1})).header("Authorization","Bearer "+hacienda)).andExpect(status().isForbidden());
        mvc.perform(get("/api/admin/hacienda")).andExpect(status().isUnauthorized());
        mvc.perform(get("/api/admin/hacienda").header("Authorization","Bearer "+admin)).andExpect(status().isOk());
        mvc.perform(post("/api/admin/hacienda").header("Authorization","Bearer "+admin).contentType(MediaType.APPLICATION_JSON).content(body("BORRADOR"))).andExpect(status().isCreated());
    }
    @Test void noPublicaSinArchivoYProtegeUltimoDocumento()throws Exception {
        long id=crear();
        mvc.perform(put("/api/admin/hacienda/"+id).header("Authorization","Bearer "+hacienda).contentType(MediaType.APPLICATION_JSON).content(body("PUBLICADA"))).andExpect(status().isBadRequest());
        String response=adjuntar(id,PDF,"x.pdf","application/pdf");Number archivo=JsonPath.read(response,"$.archivos[0].id");estado(id,"PUBLICADA");
        mvc.perform(delete("/api/admin/hacienda/"+id+"/archivos/"+archivo).header("Authorization","Bearer "+hacienda)).andExpect(status().isBadRequest());
        estado(id,"BORRADOR");mvc.perform(delete("/api/admin/hacienda/"+id+"/archivos/"+archivo).header("Authorization","Bearer "+hacienda)).andExpect(status().isOk());
    }
    @Test void previewAdminProtegidoYArchivoAjeno()throws Exception {
        long id=crear();String response=adjuntar(id,PDF,"x.pdf","application/pdf");Number a=JsonPath.read(response,"$.archivos[0].id");long otro=crear();
        mvc.perform(get("/api/admin/hacienda/"+id+"/archivos/"+a+"/contenido").header("Authorization","Bearer "+hacienda)).andExpect(status().isOk());
        mvc.perform(get("/api/admin/hacienda/"+otro+"/archivos/"+a+"/contenido").header("Authorization","Bearer "+hacienda)).andExpect(status().isNotFound());
    }
    @Test void usuariosSoloUnRolOperativo()throws Exception {
        for(String roles:List.of("\"PRENSA\",\"HACIENDA\"","\"SUPER_ADMIN\""))
            mvc.perform(post("/api/admin/usuarios").header("Authorization","Bearer "+admin).contentType(MediaType.APPLICATION_JSON)
                .content("{\"nombre\":\"Nuevo\",\"email\":\"nuevo@example.test\",\"password\":\"Test-password-1234\",\"activo\":true,\"roles\":["+roles+"]}"))
                .andExpect(status().isBadRequest());
        mvc.perform(post("/api/admin/usuarios").header("Authorization","Bearer "+admin).contentType(MediaType.APPLICATION_JSON)
            .content("{\"nombre\":\"Nuevo\",\"email\":\"nuevo@example.test\",\"password\":\"Test-password-1234\",\"activo\":true,\"roles\":[\"HACIENDA\"]}"))
            .andExpect(status().isCreated()).andExpect(jsonPath("$.password").doesNotExist());
    }
}
