package ar.gov.ensenada.backend.contenido;

import org.springframework.boot.CommandLineRunner;
import org.springframework.stereotype.Component;

import java.util.List;

@Component
public class AreaMunicipalDataInitializer implements CommandLineRunner {
    private final AreaMunicipalRepository areaRepository;

    public AreaMunicipalDataInitializer(AreaMunicipalRepository areaRepository) {
        this.areaRepository = areaRepository;
    }

    @Override
    public void run(String... args) {
        List<AreaMunicipal> areasOficiales = List.of(
                area("Secretaría de Inspección y Control Urbano", "Secretario: Marcos Omentari.", "(221) 460-0192 / (221) 615-6617", "inspeccionycontrolurbano@ensenada.gov.ar", "Presidente Perón y Sidoti", "Lunes a viernes de 8:00 a 16:00 hs"),
                area("Intendencia Municipal", "Intendente municipal: Mario Carlos Secco.", "(0221) 460-1771", "intendente@ensenada.gov.ar", "La Merced y Presidente Perón, 4.º piso", "No informado"),
                area("Secretaría de Servicios Públicos", "Secretario: Edgardo Reyes.", "(221) 469-1254", "", "Corralón, Camino Néstor Kirchner y Rivadavia", "Lunes a viernes de 7:00 a 16:00 hs"),
                area("Secretaría Privada", "Secretaria: María Celina Ferella.", "(0221) 460-1771/2", "intendente@ensenada.gov.ar", "La Merced y Presidente Perón, 4.º piso, oficinas 401 y 402", "Lunes a viernes de 8:00 a 16:00 hs"),
                area("Secretaría de Gobierno", "Secretaria: Dra. María Alejandra Sabio.", "(0221) 469-4883", "gobierno@ensenada.gov.ar", "La Merced y Presidente Perón, 1.º piso, oficina 100", "Lunes a viernes de 8:00 a 16:00 hs"),
                area("Secretaría de Hacienda y Producción", "Secretaria: Rocío Basso.", "(0221) 460-1428", "hacienda@ensenada.gov.ar", "Presidente Perón 391", "Lunes a viernes de 8:00 a 15:00 hs"),
                area("Secretaría de Desarrollo Social", "Secretaria: Celina Ferella.", "(221) 469-1265", "desarrollosocial@ensenada.gov.ar", "Leandro N. Alem y Brasil", "Lunes a viernes de 8:00 a 16:00 hs"),
                area("Secretaría de Seguridad y Justicia", "Secretario: Martín Slobodian.", "(0221) 469-3155", "", "No informada", "Lunes a viernes de 8:00 a 16:00 hs"),
                area("Secretaría de Gestión Pública", "Secretario: Agustín Duscovich.", "(221) 460-1770 / (221) 400-1504", "prensamunicipalidadensenada@gmail.com", "La Merced y Presidente Perón, 2.º piso, oficina 200", "Lunes a viernes de 8:00 a 16:00 hs"),
                area("Secretaría de Salud y Medio Ambiente", "Secretario de Salud: Juan Manuel López Ortega.", "(221) 469-0099 / (221) 469-2403", "secsaludensenada@hotmail.com", "San Martín y De La Paz", "Lunes a viernes de 8:00 a 16:00 hs"),
                area("Secretaría de Obras Públicas", "Secretario: José Alberto Núñez.", "(221) 469-3702", "obrasyservicios@ensenada.gov.ar", "La Merced y Presidente Perón, 3.º piso, oficina 300", "Lunes a viernes de 8:00 a 16:00 hs")
        );

        List<AreaMunicipal> areasFaltantes = areasOficiales.stream()
                .filter(area -> !areaRepository.existsByNombreIgnoreCase(area.getNombre()))
                .toList();

        areaRepository.saveAll(areasFaltantes);
    }

    private AreaMunicipal area(String nombre, String descripcion, String telefono, String email, String direccion, String horario) {
        AreaMunicipal area = new AreaMunicipal();
        area.setNombre(nombre);
        area.setDescripcion(descripcion);
        area.setTelefono(telefono);
        area.setEmail(email);
        area.setDireccion(direccion);
        area.setHorario(horario);
        return area;
    }
}
