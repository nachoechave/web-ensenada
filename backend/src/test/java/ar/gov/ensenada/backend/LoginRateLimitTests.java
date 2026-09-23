package ar.gov.ensenada.backend;
import ar.gov.ensenada.backend.auth.LoginRateLimiter;
import org.junit.jupiter.api.Test;
import java.time.*;
import static org.junit.jupiter.api.Assertions.*;

class LoginRateLimitTests {
    static class MutableClock extends Clock {
        long now; public ZoneId getZone(){return ZoneOffset.UTC;}public Clock withZone(ZoneId zone){return this;}public Instant instant(){return Instant.ofEpochMilli(now);}
    }
    @Test void ventanasExpiranYLasIpsSonIndependientes(){
        var clock=new MutableClock();var limiter=new LoginRateLimiter(2,60,100,clock);
        assertEquals(0,limiter.intentar("A"));assertEquals(0,limiter.intentar("A"));assertEquals(60,limiter.intentar("A"));
        assertEquals(0,limiter.intentar("B"));clock.now=60000;assertEquals(0,limiter.intentar("A"));
    }
    @Test void memoriaAcotadaNoPermiteEviccionParaEvitarLimite(){
        var clock=new MutableClock();var limiter=new LoginRateLimiter(1,60,1,clock);
        assertEquals(0,limiter.intentar("A"));assertTrue(limiter.intentar("B")>0);assertTrue(limiter.intentar("A")>0);
        clock.now=60000;assertEquals(0,limiter.intentar("B"));
    }
    @Test void rutaCodificadaNoEvadeElFiltro()throws Exception {
        var filter=new ar.gov.ensenada.backend.auth.LoginRateLimitFilter(new LoginRateLimiter(1,60,10,Clock.systemUTC()));
        var first=new org.springframework.mock.web.MockHttpServletRequest("POST","/api/auth/login");
        filter.doFilter(first,new org.springframework.mock.web.MockHttpServletResponse(),new org.springframework.mock.web.MockFilterChain());
        var encoded=new org.springframework.mock.web.MockHttpServletRequest("POST","/api/auth/%6cogin");
        var response=new org.springframework.mock.web.MockHttpServletResponse();
        filter.doFilter(encoded,response,new org.springframework.mock.web.MockFilterChain());
        assertEquals(429,response.getStatus());
    }
}
