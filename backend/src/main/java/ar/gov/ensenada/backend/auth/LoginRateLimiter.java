package ar.gov.ensenada.backend.auth;

import java.time.Clock;
import java.util.HashMap;
import java.util.Map;

/** Fixed-window limiter with bounded memory. IP is supplied by the trusted servlet connection. */
public final class LoginRateLimiter {
    private record Window(long expiresAt, int attempts) {}
    private final Map<String,Window> windows=new HashMap<>();
    private final int attempts, maxIps;
    private final long windowMillis;
    private final Clock clock;
    public LoginRateLimiter(int attempts,long windowSeconds,int maxIps,Clock clock){
        if(attempts<1 || windowSeconds<1 || maxIps<1) throw new IllegalArgumentException("Política de login inválida");
        this.attempts=attempts;this.windowMillis=Math.multiplyExact(windowSeconds,1000);this.maxIps=maxIps;this.clock=clock;
    }
    /** Zero permits; a positive value is Retry-After in seconds. */
    public synchronized long intentar(String ip){
        long now=clock.millis();
        Window window=windows.get(ip);
        if(window!=null && window.expiresAt<=now){windows.remove(ip);window=null;}
        if(window==null){
            if(windows.size()>=maxIps){
                windows.entrySet().removeIf(e->e.getValue().expiresAt<=now);
                if(windows.size()>=maxIps) return Math.max(1,windowMillis/1000);
            }
            windows.put(ip,new Window(now+windowMillis,1));return 0;
        }
        if(window.attempts>=attempts) return Math.max(1,(window.expiresAt-now+999)/1000);
        windows.put(ip,new Window(window.expiresAt,window.attempts+1));return 0;
    }
}
