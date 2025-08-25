<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Symfony\Component\HttpFoundation\Response;

class JWTAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
        {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado',
                    'error_code' => 'USER_NOT_FOUND'
                ], 401);
            }

            // Verificar que el usuario esté activo
            if (!$user->User_Activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario inactivo',
                    'error_code' => 'USER_INACTIVE'
                ], 403);
            }

            // Verificar que tenga acceso a la aplicación
            if (!$user->User_AppProd) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sin permisos para la aplicación',
                    'error_code' => 'APP_ACCESS_DENIED'
                ], 403);
            }

        } catch (TokenExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token expirado',
                'error_code' => 'TOKEN_EXPIRED'
            ], 401);
            
        } catch (TokenInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido',
                'error_code' => 'TOKEN_INVALID'
            ], 401);
            
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token requerido',
                'error_code' => 'TOKEN_REQUIRED'
            ], 401);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de autenticación',
                'error_code' => 'AUTH_ERROR'
            ], 500);
        }

        return $next($request);
    }
}