package ar.gov.ensenada.backend;
import org.junit.jupiter.api.Test;
import org.junit.jupiter.api.condition.EnabledIfEnvironmentVariable;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.boot.test.context.SpringBootTest;
import org.springframework.test.context.ActiveProfiles;
import org.springframework.jdbc.core.JdbcTemplate;
import org.flywaydb.core.Flyway;
import jakarta.persistence.EntityManagerFactory;
import static org.junit.jupiter.api.Assertions.*;

@SpringBootTest @ActiveProfiles("mysql-validation")
@EnabledIfEnvironmentVariable(named="MYSQL_VALIDATION",matches="true")
class MySqlMigrationTests {
    @Autowired JdbcTemplate jdbc;
    @Autowired Flyway flyway;
    @Autowired EntityManagerFactory entityManagerFactory;
    @Test void v1Av3EnMySqlRealConHibernateValidate(){
        assertTrue(entityManagerFactory.isOpen());
        assertEquals("3",flyway.info().current().getVersion().toString());
        assertEquals(3,flyway.info().applied().length);
        assertEquals(0,jdbc.queryForObject("SELECT COUNT(*) FROM publicaciones_hacienda",Integer.class));
        assertEquals(0,jdbc.queryForObject("SELECT COUNT(*) FROM archivos_hacienda",Integer.class));
        assertEquals(0,jdbc.queryForObject("SELECT COUNT(*) FROM usuarios WHERE token_version <> 0",Integer.class));
        assertTrue(jdbc.queryForObject("SELECT VERSION()",String.class).startsWith("8."));
    }
}
