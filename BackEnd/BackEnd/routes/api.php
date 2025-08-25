<?php
// routes/api.php - VERSIÓN 2.0 OPTIMIZADA

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CamionesController;
use App\Http\Controllers\Api\TrozasController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\SyncController;

/*
|--------------------------------------------------------------------------
| API Routes v2.0 - Sistema de Trozas Optimizado
|--------------------------------------------------------------------------
*/

// ===========================================
// HEALTH CHECK Y INFORMACIÓN GENERAL
// ===========================================

Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'API Trozas v2.0 funcionando correctamente',
        'timestamp' => now()->toISOString(),
        'version' => '2.0.0',
        'features' => [
            'largos_variables' => true,
            'diametros_22_60_cm' => true,
            'bancos_4_max' => true,
            'offline_support' => true,
            'gps_tracking' => true,
            'photo_upload' => true
        ]
    ]);
});

Route::get('/test', function () {
    try {
        // Test completo de conectividad y configuración
        $dbTest = \DB::connection()->getPdo();
        $userCount = \DB::connection('evaluacion')->table('users')->count();
        $transporteCount = \DB::connection('sqlsrv')->table('TRANSPORTES_PACK')->where('VIGENCIA', 1)->count();
        $choferCount = \DB::connection('sqlsrv')->table('CHOFERES_PACK')->where('VIGENCIA', 1)->count();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Configuración v2.0 exitosa',
            'data' => [
                'database_connected' => true,
                'users_count' => $userCount,
                'transportes_count' => $transporteCount,
                'choferes_count' => $choferCount,
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'server_time' => now()->toISOString(),
                'available_endpoints' => [
                    'auth' => 'Autenticación y gestión de usuarios',
                    'camiones' => 'Transportes, choferes y búsquedas',
                    'trozas' => 'Registro de trozas con largos variables',
                    'sync' => 'Sincronización offline/online',
                    'admin' => 'Panel administrativo'
                ]
            ]
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Error de configuración v2.0: ' . $e->getMessage(),
            'debug_info' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ], 500);
    }
});

Route::get('/version', function () {
    return response()->json([
        'api_version' => '2.0.0',
        'features' => [
            'multi_length_support' => 'Soporte para largos: 2.00, 2.50, 2.60, 3.80, 5.10 metros',
            'diameter_range' => 'Diámetros de 22 a 60 cm (incrementos de 2)',
            'bank_system' => 'Sistema de 4 bancos por camión',
            'offline_first' => 'Estrategia online-first con fallback offline robusto',
            'gps_photo' => 'Captura GPS y fotos por banco',
            'intelligent_search' => 'Búsqueda inteligente con scoring'
        ],
        'laravel_version' => app()->version(),
        'php_version' => PHP_VERSION,
        'environment' => app()->environment(),
        'server_time' => now()->toISOString()
    ]);
});

// ===========================================
// AGRUPACIÓN POR VERSIÓN DE API
// ===========================================
Route::group(['prefix' => 'v1'], function () {
    
    // Duplicar endpoints básicos en v1 por compatibilidad
    Route::get('/health', function () {
        return response()->json([
            'status' => 'OK',
            'message' => 'API Trozas v2.0 (compatible v1) funcionando',
            'timestamp' => now()->toISOString(),
            'version' => '2.0.0'
        ]);
    });

    // ===========================================
    // AUTENTICACIÓN - Sin middleware
    // ===========================================
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        
        // Endpoints protegidos de autenticación
        Route::middleware(['jwt.auth'])->group(function () {
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::get('/verify', [AuthController::class, 'verify']);
        });
    });

    // ===========================================
    // CAMIONES Y TRANSPORTES - Con autenticación JWT
    // ===========================================
    Route::middleware(['jwt.auth'])->prefix('camiones')->group(function () {
        
        // Health check específico del módulo
        Route::get('/health', [CamionesController::class, 'healthCheck']);
        
        // Sincronización completa (NUEVO ENDPOINT v2.0)
        Route::get('/sync-all', [CamionesController::class, 'syncAll']);
        
        // Gestión de transportes
        Route::get('/transportes', [CamionesController::class, 'getTransportes']);
        Route::get('/transportes/search', [CamionesController::class, 'searchTransportes']);
        Route::get('/transportes/{id}/choferes', [CamionesController::class, 'getChoferesByTransporte']);
        
        // Gestión de choferes
        Route::get('/choferes', [CamionesController::class, 'getChoferes']);
        Route::get('/choferes/search', [CamionesController::class, 'searchChoferes']);
        
        // Estadísticas
        Route::get('/stats', [CamionesController::class, 'getStats']);
        
        // Utilidades de administración
        Route::delete('/cache', [CamionesController::class, 'clearCache']);
    });

    // ===========================================
    // TROZAS - Sistema completo con largos
    // ===========================================
    Route::middleware(['jwt.auth'])->prefix('trozas')->group(function () {
        
        // Health check específico del módulo
        Route::get('/health', [TrozasController::class, 'healthCheck']);
        
        // Configuración de la aplicación
        Route::get('/config', [TrozasController::class, 'getConfiguracion']);
        
        // CRUD básico de registros
        Route::get('/', [TrozasController::class, 'index']);
        Route::post('/', [TrozasController::class, 'store']);
        Route::get('/{id}', [TrozasController::class, 'show']);
        
        // Gestión de bancos y trozas (ACTUALIZADO CON LARGOS)
        Route::post('/{id}/bancos/{banco}/trozas', [TrozasController::class, 'addTrozasToBanco']);
        Route::put('/{id}/bancos/{banco}/cerrar', [TrozasController::class, 'cerrarBanco']);
        Route::post('/{id}/bancos/{banco}/foto', [TrozasController::class, 'uploadFotoBanco']);
        
        // Cierre de registro completo
        Route::put('/{id}/cerrar', [TrozasController::class, 'cerrarRegistro']);
        
        // Endpoints de consulta avanzada
        Route::get('/{id}/bancos', function($id) {
            return response()->json([
                'success' => true,
                'message' => 'Endpoint para obtener estado de bancos (próximamente)',
                'data' => ['registro_id' => $id]
            ]);
        });
        
        Route::get('/{id}/resumen', function($id) {
            return response()->json([
                'success' => true,
                'message' => 'Endpoint para resumen completo (próximamente)',
                'data' => ['registro_id' => $id]
            ]);
        });
    });

    // ===========================================
    // SINCRONIZACIÓN - Sistema offline/online
    // ===========================================
    Route::middleware(['jwt.auth'])->prefix('sync')->group(function () {
        
        // Subida de datos offline
        Route::post('/upload', [SyncController::class, 'uploadData']);
        Route::post('/upload/foto', [SyncController::class, 'uploadFoto']);
        
        // Descarga de actualizaciones del servidor
        Route::get('/updates', [SyncController::class, 'getServerUpdates']);
        
        // Estado de sincronización
        Route::get('/status', [SyncController::class, 'getSyncStatus']);
        Route::get('/history', [SyncController::class, 'getSyncHistory']);
        
        // Limpieza y mantenimiento
        Route::delete('/cleanup', [SyncController::class, 'cleanupSyncLogs']);
    });

    // ===========================================
    // ADMINISTRACIÓN - Con permisos especiales
    // ===========================================
    Route::middleware(['jwt.auth', 'admin.access'])->prefix('admin')->group(function () {
        
        // Dashboard y estadísticas generales
        Route::get('/dashboard', [AdminController::class, 'dashboard']);
        Route::get('/stats/registros', [AdminController::class, 'getRegistrosStats']);
        Route::get('/stats/transportes', [AdminController::class, 'getTransportesStats']);
        
        // Gestión de usuarios
        Route::get('/users', [AdminController::class, 'getUsers']);
        Route::get('/groups', [AdminController::class, 'getGroups']);
        Route::get('/modules', [AdminController::class, 'getModules']);
        
        // Monitoreo en tiempo real
        Route::get('/registros/recientes', [AdminController::class, 'getRegistrosRecientes']);
        
        // Sincronización avanzada
        Route::get('/sync/overview', [AdminController::class, 'getSyncOverview']);
    });

    // ===========================================
    // ENDPOINTS DE DESARROLLO Y DEBUG
    // ===========================================
    Route::middleware(['jwt.auth'])->prefix('dev')->group(function () {
        
        // Solo disponible en entornos de desarrollo
        Route::get('/test-data', function() {
            if (!app()->environment(['local', 'development', 'testing'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint solo disponible en desarrollo'
                ], 403);
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'diametros_disponibles' => \App\Models\TrozaDetail::getDiametrosDisponibles(),
                    'largos_disponibles' => \App\Models\TrozaDetail::getLargosDisponibles(),
                    'combinaciones_totales' => count(\App\Models\TrozaDetail::getCombinacionesDisponibles()),
                    'ejemplo_combinaciones' => array_slice(\App\Models\TrozaDetail::getCombinacionesDisponibles(), 0, 5)
                ]
            ]);
        });
        
        Route::get('/clear-all-cache', function() {
            if (!app()->environment(['local', 'development'])) {
                return response()->json(['success' => false, 'message' => 'No disponible en producción'], 403);
            }
            
            \Illuminate\Support\Facades\Cache::flush();
            return response()->json(['success' => true, 'message' => 'Cache limpiado completamente']);
        });
    });

    // ===========================================
    // ENDPOINTS TEMPORALES PARA TESTING
    // ===========================================
    
    // Rutas sin autenticación para desarrollo inicial
    Route::prefix('temp')->group(function () {
        Route::get('/transportes', [CamionesController::class, 'getTransportes']);
        Route::get('/choferes', [CamionesController::class, 'getChoferes']);
        Route::get('/choferes/search', [CamionesController::class, 'searchChoferes']);
        Route::get('/transportes/{id}/choferes', [CamionesController::class, 'getChoferesByTransporte']);
        
        // Nota: Estos endpoints deben eliminarse en producción
        Route::get('/warning', function() {
            return response()->json([
                'warning' => 'Endpoints temporales activos',
                'message' => 'Estos endpoints deben eliminarse en producción',
                'environment' => app()->environment()
            ]);
        });
    });
});

// ===========================================
// MANEJO DE RUTAS NO ENCONTRADAS
// ===========================================
Route::fallback(function (Request $request) {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint no encontrado',
        'error_code' => 'ROUTE_NOT_FOUND',
        'requested_path' => $request->path(),
        'available_versions' => ['v1'],
        'documentation' => 'Consulte la documentación de la API v2.0',
        'help' => [
            'health_check' => '/api/health',
            'version_info' => '/api/version',
            'test_connection' => '/api/test'
        ]
    ], 404);
});

// ===========================================
// RUTAS DE COMPATIBILIDAD (SIN VERSIÓN)
// ===========================================

// Redirects a v1 para compatibilidad
Route::get('/camiones/{path}', function($path) {
    return response()->json([
        'message' => 'Use /api/v1/camiones/' . $path,
        'redirect' => url('/api/v1/camiones/' . $path)
    ], 301);
})->where('path', '.*');

Route::get('/trozas/{path}', function($path) {
    return response()->json([
        'message' => 'Use /api/v1/trozas/' . $path,
        'redirect' => url('/api/v1/trozas/' . $path)
    ], 301);
})->where('path', '.*');

// Auth directo (sin versión) para compatibilidad
Route::post('/auth/login', [AuthController::class, 'login']);