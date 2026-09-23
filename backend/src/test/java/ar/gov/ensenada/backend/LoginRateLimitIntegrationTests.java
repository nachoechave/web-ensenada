package ar.gov.ensenada.backend;
import org.junit.jupiter.api.Test;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.boot.webmvc.test.autoconfigure.AutoConfigureMockMvc;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.test.web.servlet.MockMvc;
import org.springframework.http.MediaType;
import static org.springframework.test.web.servlet.request.MockMvcRequestBuilders.*;
import static org.springframework.test.web.servlet.result.MockMvcResultMatchers.*;

@SpringBootTest(properties="app.login-rate-limit.attempts=2") @AutoConfigureMockMvc @ActiveProfiles("test")
class LoginRateLimitIntegrationTests {
    @Autowired MockMvc mvc;
    @Test void excesoDevuelve429YNoConfiaEnForwardedFor()throws Exception {
        for(int i=0;i<2;i++)mvc.perform(post("/api/auth/login").contentType(MediaType.APPLICATION_JSON).content("{\"email\":\"noexiste@example.test\",\"password\":\"invalid\"}"))
            .andExpect(status().isUnauthorized());
        mvc.perform(post("/api/auth/login").header("X-Forwarded-For","192.0.2.123").contentType(MediaType.APPLICATION_JSON).content("{}"))
            .andExpect(status().isTooManyRequests()).andExpect(header().exists("Retry-After"));
        mvc.perform(get("/api/hacienda")).andExpect(status().isOk());
    }
}
