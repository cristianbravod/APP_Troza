<?php
// app/Http/Middleware/RateLimitMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => 'Demasiadas peticiones. Intente nuevamente en ' . $retryAfter . ' segundos',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'retry_after' => $retryAfter
            ], 429);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        // Agregar headers informativos
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($key, $maxAttempts),
            'X-RateLimit-Reset' => RateLimiter::availableIn($key) + time(),
        ]);
        
        return $response;
    }
    
    /**
     * Resolve request signature.
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $userId = auth()->id();
        $ip = $request->ip();
        $route = $request->route()?->getName() ?? 'unknown';
        
        return sha1($userId . '|' . $ip . '|' . $route);
    }
}