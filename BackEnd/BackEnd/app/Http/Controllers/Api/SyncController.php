<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use App\Models\TrozaHead;
use App\Models\TrozaDetail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SyncController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * Subir datos offline (registros y detalles)
     */
    public function uploadData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:100',
            'registros' => 'required|array',
            'registros.*.patente_camion' => 'required|string|max:10',
            'registros.*.id_chofer' => 'required|integer',
            'registros.*.id_transporte' => 'required|integer',
            'registros.*.fecha_inicio' => 'required|date',
            'registros.*.estado' => 'required|string|in:ABIERTO,CERRADO',
            'registros.*.observaciones' => 'nullable|string|max:500',
            'registros.*.detalles' => 'nullable|array',
            'registros.*.detalles.*.numero_banco' => 'required|integer|between:1,4',
            'registros.*.detalles.*.diametro_cm' => 'required|integer|between:22,60',
            'registros.*.detalles.*.cantidad_trozas' => 'required|integer|min:0',
            'registros.*.detalles.*.banco_cerrado' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación en los datos',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = auth()->id();
        $deviceId = $request->device_id;
        $syncResults = [];

        try {
            DB::beginTransaction();

            foreach ($request->registros as $registroData) {
                try {
                    // Verificar si ya existe (evitar duplicados)
                    $existeRegistro = TrozaHead::where('PATENTE_CAMION', $registroData['patente_camion'])
                                              ->where('USER_ID', $userId)
                                              ->where('FECHA_INICIO', $registroData['fecha_inicio'])
                                              ->first();

                    if ($existeRegistro) {
                        $syncResults[] = [
                            'local_id' => $registroData['local_id'] ?? null,
                            'server_id' => $existeRegistro->ID_REGISTRO,
                            'status' => 'duplicate',
                            'message' => 'Registro ya existe en el servidor'
                        ];
                        continue;
                    }

                    // Crear registro principal
                    $registro = TrozaHead::create([
                        'PATENTE_CAMION' => strtoupper($registroData['patente_camion']),
                        'ID_CHOFER' => $registroData['id_chofer'],
                        'ID_TRANSPORTE' => $registroData['id_transporte'],
                        'FECHA_INICIO' => $registroData['fecha_inicio'],
                        'FECHA_CIERRE' => $registroData['fecha_cierre'] ?? null,
                        'ESTADO' => $registroData['estado'],
                        'USER_ID' => $userId,
                        'OBSERVACIONES' => $registroData['observaciones'] ?? null,
                        'CREATED_AT' => now(),
                        'UPDATED_AT' => now()
                    ]);

                    // Crear detalles si existen
                    if (isset($registroData['detalles']) && is_array($registroData['detalles'])) {
                        foreach ($registroData['detalles'] as $detalle) {
                            TrozaDetail::create([
                                'ID_REGISTRO' => $registro->ID_REGISTRO,
                                'NUMERO_BANCO' => $detalle['numero_banco'],
                                'DIAMETRO_CM' => $detalle['diametro_cm'],
                                'CANTIDAD_TROZAS' => $detalle['cantidad_trozas'],
                                'FECHA_CIERRE_BANCO' => $detalle['fecha_cierre_banco'] ?? null,
                                'GPS_LATITUD' => $detalle['gps_latitud'] ?? null,
                                'GPS_LONGITUD' => $detalle['gps_longitud'] ?? null,
                                'BANCO_CERRADO' => $detalle['banco_cerrado'] ?? false,
                                'CREATED_AT' => now()
                            ]);
                        }
                        
                        // Recalcular total de trozas
                        $registro->calcularTotalTrozas();
                    }

                    // Log de sincronización exitosa
                    SyncLog::logUpload(
                        $userId,
                        $deviceId,
                        SyncLog::ENTITY_TYPE_REGISTRO,
                        $registro->ID_REGISTRO,
                        SyncLog::STATUS_SUCCESS
                    );

                    $syncResults[] = [
                        'local_id' => $registroData['local_id'] ?? null,
                        'server_id' => $registro->ID_REGISTRO,
                        'status' => 'success',
                        'message' => 'Registro sincronizado exitosamente'
                    ];

                } catch (\Exception $e) {
                    // Log de error
                    SyncLog::logUpload(
                        $userId,
                        $deviceId,
                        SyncLog::ENTITY_TYPE_REGISTRO,
                        0,
                        SyncLog::STATUS_ERROR,
                        $e->getMessage()
                    );

                    $syncResults[] = [
                        'local_id' => $registroData['local_id'] ?? null,
                        'status' => 'error',
                        'message' => 'Error al sincronizar: ' . $e->getMessage()
                    ];
                }
            }

            DB::commit();

            $successCount = collect($syncResults)->where('status', 'success')->count();
            $errorCount = collect($syncResults)->where('status', 'error')->count();
            $duplicateCount = collect($syncResults)->where('status', 'duplicate')->count();

            return response()->json([
                'success' => true,
                'message' => 'Sincronización completada',
                'data' => [
                    'results' => $syncResults,
                    'summary' => [
                        'total' => count($syncResults),
                        'success' => $successCount,
                        'errors' => $errorCount,
                        'duplicates' => $duplicateCount
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error general en la sincronización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Subir foto individual
     */
    public function uploadFoto(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,jpg,png|max:10240',
            'registro_id' => 'required|integer|exists:ABAS_Troza_HEAD,ID_REGISTRO',
            'numero_banco' => 'required|integer|between:1,4',
            'gps_latitud' => 'nullable|numeric|between:-90,90',
            'gps_longitud' => 'nullable|numeric|between:-180,180'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Errores de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Verificar que el registro pertenece al usuario
            $registro = TrozaHead::where('ID_REGISTRO', $request->registro_id)
                                 ->where('USER_ID', auth()->id())
                                 ->first();

            if (!$registro) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registro no encontrado o sin permisos'
                ], 404);
            }

            DB::beginTransaction();

            // Subir foto
            $foto = $request->file('foto');
            $nombreArchivo = 'sync_banco_' . $request->registro_id . '_' . $request->numero_banco . '_' . time() . '.' . $foto->getClientOriginalExtension();
            $fotoPath = $foto->storeAs('fotos', $nombreArchivo, 'public');

            // Actualizar detalles del banco con la foto
            $updated = TrozaDetail::where('ID_REGISTRO', $request->registro_id)
                                  ->where('NUMERO_BANCO', $request->numero_banco)
                                  ->update([
                                      'FOTO_PATH' => $fotoPath,
                                      'GPS_LATITUD' => $request->gps_latitud,
                                      'GPS_LONGITUD' => $request->gps_longitud
                                  ]);

            if ($updated === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron detalles del banco para actualizar'
                ], 404);
            }

            // Log de sincronización
            SyncLog::logUpload(
                auth()->id(),
                $request->header('Device-ID', 'mobile'),
                SyncLog::ENTITY_TYPE_FOTO,
                $request->registro_id,
                SyncLog::STATUS_SUCCESS
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Foto subida exitosamente',
                'data' => [
                    'foto_url' => Storage::url($fotoPath),
                    'registro_id' => $request->registro_id,
                    'numero_banco' => $request->numero_banco
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al subir foto: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener actualizaciones del servidor
     */
    public function getServerUpdates(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'last_sync' => 'nullable|date',
            'device_id' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parámetros inválidos',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lastSync = $request->last_sync ? 
                \Carbon\Carbon::parse($request->last_sync) : 
                now()->subDays(30);

            $userId = auth()->id();

            // Obtener registros actualizados desde la última sincronización
            $registros = TrozaHead::with(['detalles', 'chofer', 'transporte'])
                                  ->where('USER_ID', $userId)
                                  ->where('UPDATED_AT', '>', $lastSync)
                                  ->orderBy('UPDATED_AT', 'desc')
                                  ->get();

            // Obtener configuraciones o datos maestros si es necesario
            $configuracion = [
                'diametros_disponibles' => TrozaDetail::getDiametrosDisponibles(),
                'version_api' => '1.0.0',
                'tiempo_sesion' => config('jwt.ttl'),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'registros' => $registros,
                    'configuracion' => $configuracion,
                    'server_time' => now()->toISOString(),
                    'total_registros' => $registros->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener actualizaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estado de sincronización
     */
    public function getSyncStatus(): JsonResponse
    {
        try {
            $userId = auth()->id();
            
            // Contadores de sincronización
            $pendingUploads = SyncLog::where('USER_ID', $userId)
                                    ->where('SYNC_STATUS', SyncLog::STATUS_PENDING)
                                    ->count();

            $recentErrors = SyncLog::where('USER_ID', $userId)
                                  ->where('SYNC_STATUS', SyncLog::STATUS_ERROR)
                                  ->where('CREATED_AT', '>', now()->subHours(24))
                                  ->count();

            $successfulSyncs = SyncLog::where('USER_ID', $userId)
                                     ->where('SYNC_STATUS', SyncLog::STATUS_SUCCESS)
                                     ->where('CREATED_AT', '>', now()->subDays(7))
                                     ->count();

            $lastSync = SyncLog::where('USER_ID', $userId)
                              ->where('SYNC_STATUS', SyncLog::STATUS_SUCCESS)
                              ->orderBy('PROCESSED_AT', 'desc')
                              ->first();

            // Estadísticas de registros del usuario
            $totalRegistros = TrozaHead::where('USER_ID', $userId)->count();
            $registrosAbiertos = TrozaHead::where('USER_ID', $userId)
                                         ->where('ESTADO', 'ABIERTO')
                                         ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'sync_status' => [
                        'pending_uploads' => $pendingUploads,
                        'recent_errors' => $recentErrors,
                        'successful_syncs_week' => $successfulSyncs,
                        'last_sync' => $lastSync ? $lastSync->PROCESSED_AT->toISOString() : null
                    ],
                    'user_stats' => [
                        'total_registros' => $totalRegistros,
                        'registros_abiertos' => $registrosAbiertos,
                        'registros_cerrados' => $totalRegistros - $registrosAbiertos
                    ],
                    'server_time' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado de sincronización: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener historial de sincronización
     */
    public function getSyncHistory(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 50);
            $days = $request->get('days', 7);

            $history = SyncLog::where('USER_ID', auth()->id())
                             ->where('CREATED_AT', '>', now()->subDays($days))
                             ->orderBy('CREATED_AT', 'desc')
                             ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener historial: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar logs de sincronización antiguos
     */
    public function cleanupSyncLogs(): JsonResponse
    {
        try {
            $userId = auth()->id();
            $daysToKeep = 30;

            $deleted = SyncLog::where('USER_ID', $userId)
                             ->where('CREATED_AT', '<', now()->subDays($daysToKeep))
                             ->delete();

            return response()->json([
                'success' => true,
                'message' => "Se eliminaron {$deleted} registros de sincronización antiguos",
                'data' => ['deleted_count' => $deleted]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar logs: ' . $e->getMessage()
            ], 500);
        }
    }
}