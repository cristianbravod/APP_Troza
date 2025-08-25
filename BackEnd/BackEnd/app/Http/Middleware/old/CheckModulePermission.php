<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckModulePermission
{
    public function handle(Request $request, Closure $next, $moduleName)
    {
        $user = auth()->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado'
            ], 401);
        }

        // Para usuarios admin, permitir acceso a todo
        if ($user->groups()->where('name', 'admin')->exists()) {
            return $next($request);
        }

        // Verificar permisos específicos del módulo
        if (!$user->hasModuleAccess($moduleName)) {
            return response()->json([
                'success' => false,
                'message' => 'No tiene permisos para acceder a este módulo'
            ], 403);
        }

        return $next($request);
    }
}
