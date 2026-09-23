package ar.gov.ensenada.backend.auth;

import jakarta.servlet.*;
import jakarta.servlet.http.*;
import org.springframework.web.filter.OncePerRequestFilter;
import java.io.IOException;

public class LoginRateLimitFilter extends OncePerRequestFilter {
    private final LoginRateLimiter limiter;
    public LoginRateLimitFilter(LoginRateLimiter limiter){this.limiter=limiter;}
    @Override protected boolean shouldNotFilter(HttpServletRequest request){
        String path=org.springframework.web.util.UrlPathHelper.defaultInstance.getPathWithinApplication(request);
        return !"POST".equals(request.getMethod()) || !"/api/auth/login".equals(path);
    }
    @Override protected void doFilterInternal(HttpServletRequest request,HttpServletResponse response,FilterChain chain) throws ServletException,IOException {
        long retry=limiter.intentar(request.getRemoteAddr());
        if(retry>0){
            response.setStatus(429); response.setHeader("Retry-After",Long.toString(retry));
            response.setContentType("application/json");response.setCharacterEncoding("UTF-8");
            response.getWriter().write("{\"mensaje\":\"Demasiados intentos. Esperá antes de volver a iniciar sesión.\"}");return;
        }
        chain.doFilter(request,response);
    }
}
