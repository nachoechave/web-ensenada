package ar.gov.ensenada.backend;

import ar.gov.ensenada.backend.auth.RolUsuario;
import ar.gov.ensenada.backend.auth.Usuario;
import ar.gov.ensenada.backend.auth.UsuarioRepository;
import com.jayway.jsonpath.JsonPath;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.webmvc.test.autoconfigure.AutoConfigureMockMvc;
import org.springframework.http.MediaType;
import org.springframework.security.crypto.password.PasswordEncoder;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;

import java.util.Set;

import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.get;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.post;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.put;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.jsonPath;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.status;

@SpringBootTest
@AutoConfigureMockMvc
@ActiveProfiles("test")
class PortalSiteContentIntegrationTests {

    @Autowired MockMvc mvc;
    @Autowired UsuarioRepository usuarios;
    @Autowired PasswordEncoder encoder;

    String admin;
    final String password = "Test-password-1234";

    @BeforeEach
    void setup() throws Exception {
        usuarios.deleteAll();
        usuarios.save(new Usuario(
                "Admin",
                "admin-cms@example.test",
                encoder.encode(password),
                Set.of(RolUsuario.SUPER_ADMIN),
                true
        ));
        admin = login();
    }

    private String login() throws Exception {
        String response = mvc.perform(post("/api/auth/login")
                        .contentType(MediaType.APPLICATION_JSON)
                        .content("{\"email\":\"admin-cms@example.test\",\"password\":\"" + password + "\"}"))
                .andExpect(status().isOk())
                .andReturn()
                .getResponse()
                .getContentAsString();

        return JsonPath.read(response, "$.token");
    }

    @Test
    void guardaYPublicaCamposEditadosDeSecretarias() throws Exception {
        String body = """
            {
              "areas": {
                "kicker": "Áreas municipales",
                "titulo": "Conocé nuestras áreas",
                "bajada": "Información municipal",
                "paginaTitulo": "Secretarías",
                "paginaBajada": "Directorio",
                "items": [],
                "secretarias": [
                  {
                    "id": "seguridad-justicia",
                    "nombre": "Secretaría de Seguridad y Justicia",
                    "responsable": "Martín Slobodian",
                    "direccion": "Calle Nueva 123",
                    "telefono": "(221) 555-9999",
                    "horario": "Lunes a viernes de 9 a 15",
                    "email": "seguridad@example.test"
                  }
                ]
              }
            }
            """;

        mvc.perform(put("/api/admin/site-content")
                        .header("Authorization", "Bearer " + admin)
                        .contentType(MediaType.APPLICATION_JSON)
                        .content(body))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.areas.secretarias[0].direccion").value("Calle Nueva 123"))
                .andExpect(jsonPath("$.areas.secretarias[0].telefono").value("(221) 555-9999"));

        mvc.perform(get("/api/admin/site-content")
                        .header("Authorization", "Bearer " + admin))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.areas.secretarias[0].direccion").value("Calle Nueva 123"))
                .andExpect(jsonPath("$.areas.secretarias[0].telefono").value("(221) 555-9999"));

        mvc.perform(get("/api/site-content"))
                .andExpect(status().isOk())
                .andExpect(jsonPath("$.areas.secretarias[0].direccion").value("Calle Nueva 123"))
                .andExpect(jsonPath("$.areas.secretarias[0].telefono").value("(221) 555-9999"));
    }
}
