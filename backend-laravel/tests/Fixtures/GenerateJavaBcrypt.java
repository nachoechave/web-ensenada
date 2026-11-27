import org.springframework.security.crypto.bcrypt.BCrypt;

/** Synthetic vectors only. Run in the disposable Maven/Java 21 container. */
public class GenerateJavaBcrypt {
    public static void main(String[] args) {
        String[] values = {"Synthetic-password-1234", "Contraseña-sintética-1234", "a".repeat(72)};
        System.out.println("{\"generator\":\"Spring Security BCrypt 7.1.0 / Java 21\",\"vectors\":[");
        for (int i = 0; i < values.length; i++) {
            String hash = BCrypt.hashpw(values[i], BCrypt.gensalt(10));
            if (!BCrypt.checkpw(values[i], hash)) throw new AssertionError("Invalid Java vector");
            System.out.print("{\"password\":\"" + values[i] + "\",\"hash\":\"" + hash + "\"}");
            System.out.println(i + 1 < values.length ? "," : "");
        }
        System.out.println("]}");
    }
}
