<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    /**
     * Login de usuario compatible con sistema existente
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user' => 'required|string',
            'pass' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Buscar usuario por username o email
            $user = User::where(function($query) use ($request) {
                        $query->where('username', $request->user)
                              ->orWhere('email', $request->user);
                    })
                    ->where('User_Activo', true)
                    ->where('User_AppProd', true)
                    ->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado o no tiene permisos para la aplicación'
                ], 401);
            }

            // Verificar contraseña
            if (!$user->verifyPassword($request->pass)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Credenciales incorrectas'
                ], 401);
            }

            // Actualizar último login
            $user->update(['last_login' => time()]);

            // Generar token JWT
            $token = JWTAuth::fromUser($user);
            
            // Cargar relaciones necesarias
            $user->load(['groups' => function($query) {
                $query->active()->appProd();
            }, 'groups.modules' => function($query) {
                $query->active()->ordered();
            }]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'company' => $user->company,
                        'phone' => $user->phone,
                        'full_name' => trim($user->first_name . ' ' . $user->last_name)
                    ],
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60,
                    'groups' => $user->groups->map(function($group) {
                        return [
                            'id' => $group->id,
                            'name' => $group->name,
                            'description' => $group->description
                        ];
                    }),
                    'modules' => $user->groups->flatMap->modules->unique('id')->map(function($module) {
                        return [
                            'id' => $module->id,
                            'name' => $module->NAME,
                            'description' => $module->DESCRIPTION,
                            'url' => $module->URL,
                            'icon' => $module->ICON,
                            'priority' => $module->PRIORITY
                        ];
                    })->values()
                ]
            ]);

        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar token de autenticación'
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    /**
     * Refrescar token JWT
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh();
            return response()->json([
                'success' => true,
                'data' => [
                    'token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token no válido para renovar'
            ], 401);
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout(): JsonResponse
    {
        try {
            JWTAuth::invalidate();
            return response()->json([
                'success' => true,
                'message' => 'Sesión cerrada correctamente'
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cerrar sesión'
            ], 500);
        }
    }

    /**
     * Obtener información del usuario autenticado
     */
    public function me(): JsonResponse
    {
        try {
            $user = auth()->user();
            $user->load(['groups.modules']);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'company' => $user->company,
                        'phone' => $user->phone,
                        'full_name' => trim($user->first_name . ' ' . $user->last_name)
                    ],
                    'groups' => $user->groups->map(function($group) {
                        return [
                            'id' => $group->id,
                            'name' => $group->name,
                            'description' => $group->description
                        ];
                    }),
                    'permissions' => $user->getModuleNames(),
                    'last_login' => $user->last_login ? date('Y-m-d H:i:s', $user->last_login) : null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener información del usuario'
            ], 500);
        }
    }

    /**
     * Verificar si el token es válido
     */
    public function verify(): JsonResponse
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            
            if (!$user || !$user->User_Activo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido o usuario inactivo'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'message' => 'Token válido',
                'data' => [
                    'user_id' => $user->id,
                    'username' => $user->username
                ]
            ]);
        } catch (JWTException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token inválido'
            ], 401);
        }
    }
}
