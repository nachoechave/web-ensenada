package ar.gov.ensenada.backend.admin;

import org.springframework.security.core.Authentication;
import org.springframework.web.bind.annotation.*;

import java.util.List;
import java.util.Map;

@RestController
@RequestMapping("/api/admin")
public class AdminController {

    @GetMapping("/test")
    public Map<String, Object> test(Authentication authentication) {
        List<String> roles = authentication.getAuthorities()
                .stream()
                .map(Object::toString)
                .toList();

        return Map.of(
                "mensaje", "Acceso admin autorizado",
                "usuario", authentication.getName(),
                "roles", roles
        );
    }
}
