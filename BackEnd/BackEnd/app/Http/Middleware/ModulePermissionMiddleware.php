<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ModulePermissionMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $moduleName)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado',
                'error_code' => 'NOT_AUTHENTICATED'
            ], 401);
        }

        // Para usuarios admin, permitir acceso a todo
        $isAdmin = $user->groups()->where('name', 'admin')->exists() ||
                   $user->groups()->where('name', 'administrador')->exists();

        if ($isAdmin) {
            return $next($request);
        }

        // Verificar permisos específicos del módulo
        if (!$user->hasModuleAccess($moduleName)) {
            return response()->json([
                'success' => false,
                'message' => "No tiene permisos para acceder al módulo: {$moduleName}",
                'error_code' => 'MODULE_ACCESS_DENIED',
                'required_module' => $moduleName
            ], 403);
        }

        return $next($request);
    }
}