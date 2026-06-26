package ar.gov.ensenada.backend.admin;

import org.springframework.web.bind.annotation.*;

import java.util.Map;

@RestController
@RequestMapping("/api/admin")
public class AdminController {

    @GetMapping("/test")
    public Map<String, String> test() {
        return Map.of("mensaje", "Acceso admin autorizado");
    }
}