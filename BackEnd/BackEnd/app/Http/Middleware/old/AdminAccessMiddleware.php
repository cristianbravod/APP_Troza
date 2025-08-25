<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
                'error_code' => 'NOT_AUTHENTICATED'
            ], 401);
        }

        // Verificar si el usuario tiene rol de administrador
        $isAdmin = $user->groups()->where('name', 'admin')->exists() ||
                   $user->groups()->where('name', 'administrador')->exists() ||
                   $user->hasModuleAccess('ADMIN');

        if (!$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Acceso denegado. Se requieren permisos de administrador',
                'error_code' => 'ADMIN_ACCESS_REQUIRED'
            ], 403);
        }

        return $next($request);
    }
}