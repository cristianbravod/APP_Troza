<?php
// app/Services/SyncService.php

namespace App\Services;

use App\Models\TrozaHead;
use App\Models\TrozaDetail;
use App\Models\SyncLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncService
{
    /**
     * Procesar datos de sincronización desde dispositivos móviles
     */
    public function processBulkUpload(array $data, int $userId, string $deviceId): array
    {
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        $duplicateCount = 0;

        DB::beginTransaction();
        
        try {
            foreach ($data['registros'] as $registroData) {
                $result = $this->processRegistro($registroData, $userId, $deviceId);
                $results[] = $result;
                
                switch ($result['status']) {
                    case 'success':
                        $successCount++;
                        break;
                    case 'error':
                        $errorCount++;
                        break;
                    case 'duplicate':
                        $duplicateCount++;
                        break;
                }
            }

            DB::commit();
            
            return [
                'success' => true,
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'success' => $successCount,
                    'errors' => $errorCount,
                    'duplicates' => $duplicateCount
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error en sincronización masiva', [
                'user_id' => $userId,
                'device_id' => $deviceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Procesar un registro individual
     */
    private function processRegistro(array $registroData, int $userId, string $deviceId): array
    {
        try {
            // Verificar duplicados
            $existe = TrozaHead::where('PATENTE_CAMION', $registroData['patente_camion'])
                              ->where('USER_ID', $userId)
                              ->where('FECHA_INICIO', $registroData['fecha_inicio'])
                              ->first();

            if ($existe) {
                return [
                    'local_id' => $registroData['local_id'] ?? null,
                    'server_id' => $existe->ID_REGISTRO,
                    'status' => 'duplicate',
                    'message' => 'Registro ya existe en el servidor'
                ];
            }

            // Crear registro principal
            $registro = TrozaHead::create([
                'PATENTE_CAMION' => strtoupper($registroData['patente_camion']),
                'ID_CHOFER' => $registroData['id_chofer'],
                'ID_TRANSPORTE' => $registroData['id_transporte'],
                'FECHA_INICIO' => $registroData['fecha_inicio'],
                'FECHA_CIERRE' => $registroData['fecha_cierre'] ?? null,
                'ESTADO' => $registroData['estado'] ?? 'ABIERTO',
                'USER_ID' => $userId,
                'OBSERVACIONES' => $registroData['observaciones'] ?? null,
                'CREATED_AT' => now(),
                'UPDATED_AT' => now()
            ]);

            // Procesar detalles si existen
            if (!empty($registroData['detalles'])) {
                $this->processDetalles($registro->ID_REGISTRO, $registroData['detalles']);
                $registro->calcularTotalTrozas();
            }

            // Log de sincronización exitosa
            SyncLog::logUpload($userId, $deviceId, SyncLog::ENTITY_TYPE_REGISTRO, $registro->ID_REGISTRO, SyncLog::STATUS_SUCCESS);

            return [
                'local_id' => $registroData['local_id'] ?? null,
                'server_id' => $registro->ID_REGISTRO,
                'status' => 'success',
                'message' => 'Registro sincronizado exitosamente'
            ];

        } catch (\Exception $e) {
            SyncLog::logUpload($userId, $deviceId, SyncLog::ENTITY_TYPE_REGISTRO, 0, SyncLog::STATUS_ERROR, $e->getMessage());
            
            return [
                'local_id' => $registroData['local_id'] ?? null,
                'status' => 'error',
                'message' => 'Error al sincronizar: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Procesar detalles de un registro
     */
    private function processDetalles(int $registroId, array $detalles): void
    {
        foreach ($detalles as $detalle) {
            TrozaDetail::create([
                'ID_REGISTRO' => $registroId,
                'NUMERO_BANCO' => $detalle['numero_banco'],
                'DIAMETRO_CM' => $detalle['diametro_cm'],
                'CANTIDAD_TROZAS' => $detalle['cantidad_trozas'],
                'FECHA_CIERRE_BANCO' => $detalle['fecha_cierre_banco'] ?? null,
                'FOTO_PATH' => $detalle['foto_path'] ?? null,
                'GPS_LATITUD' => $detalle['gps_latitud'] ?? null,
                'GPS_LONGITUD' => $detalle['gps_longitud'] ?? null,
                'BANCO_CERRADO' => $detalle['banco_cerrado'] ?? false,
                'CREATED_AT' => now()
            ]);
        }
    }

    /**
     * Procesar subida de foto
     */
    public function processFotoUpload($foto, int $registroId, int $numeroBanco, ?float $gpsLat, ?float $gpsLng, int $userId, string $deviceId): array
    {
        try {
            DB::beginTransaction();

            // Verificar que el registro existe y pertenece al usuario
            $registro = TrozaHead::where('ID_REGISTRO', $registroId)
                                 ->where('USER_ID', $userId)
                                 ->first();

            if (!$registro) {
                throw new \Exception('Registro no encontrado o sin permisos');
            }

            // Subir foto
            $nombreArchivo = $this->generateFotoName($registroId, $numeroBanco);
            $fotoPath = $foto->storeAs('fotos', $nombreArchivo, 'public');

            // Actualizar detalles del banco
            $updated = TrozaDetail::where('ID_REGISTRO', $registroId)
                                  ->where('NUMERO_BANCO', $numeroBanco)
                                  ->update([
                                      'FOTO_PATH' => $fotoPath,
                                      'GPS_LATITUD' => $gpsLat,
                                      'GPS_LONGITUD' => $gpsLng,
                                      'FECHA_CIERRE_BANCO' => now()
                                  ]);

            if ($updated === 0) {
                throw new \Exception('No se encontraron detalles del banco para actualizar');
            }

            // Log de sincronización
            SyncLog::logUpload($userId, $deviceId, SyncLog::ENTITY_TYPE_FOTO, $registroId, SyncLog::STATUS_SUCCESS);

            DB::commit();

            return [
                'success' => true,
                'foto_url' => Storage::url($fotoPath),
                'registro_id' => $registroId,
                'numero_banco' => $numeroBanco
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            SyncLog::logUpload($userId, $deviceId, SyncLog::ENTITY_TYPE_FOTO, $registroId, SyncLog::STATUS_ERROR, $e->getMessage());
            
            throw $e;
        }
    }

    /**
     * Obtener actualizaciones desde el servidor
     */
    public function getServerUpdates(int $userId, ?string $lastSync = null): array
    {
        $lastSyncDate = $lastSync ? 
            Carbon::parse($lastSync) : 
            now()->subDays(30);

        // Obtener registros actualizados
        $registros = TrozaHead::with(['detalles', 'chofer', 'transporte'])
                              ->where('USER_ID', $userId)
                              ->where('UPDATED_AT', '>', $lastSyncDate)
                              ->orderBy('UPDATED_AT', 'desc')
                              ->get();

        // Configuraciones del servidor
        $configuracion = [
            'diametros_disponibles' => TrozaDetail::getDiametrosDisponibles(),
            'max_foto_size' => 10240, // KB
            'bancos_por_camion' => 4,
            'version_api' => '1.0.0',
            'tiempo_sesion' => config('jwt.ttl', 60)
        ];

        return [
            'registros' => $registros,
            'configuracion' => $configuracion,
            'server_time' => now()->toISOString(),
            'total_registros' => $registros->count()
        ];
    }

    /**
     * Obtener estadísticas de sincronización
     */
    public function getSyncStats(int $userId): array
    {
        $stats = [
            'pending_uploads' => SyncLog::where('USER_ID', $userId)
                                       ->where('SYNC_STATUS', SyncLog::STATUS_PENDING)
                                       ->count(),
            'recent_errors' => SyncLog::where('USER_ID', $userId)
                                     ->where('SYNC_STATUS', SyncLog::STATUS_ERROR)
                                     ->where('CREATED_AT', '>', now()->subHours(24))
                                     ->count(),
            'successful_syncs_week' => SyncLog::where('USER_ID', $userId)
                                             ->where('SYNC_STATUS', SyncLog::STATUS_SUCCESS)
                                             ->where('CREATED_AT', '>', now()->subDays(7))
                                             ->count(),
            'last_sync' => SyncLog::where('USER_ID', $userId)
                                  ->where('SYNC_STATUS', SyncLog::STATUS_SUCCESS)
                                  ->latest('PROCESSED_AT')
                                  ->value('PROCESSED_AT')
        ];

        return $stats;
    }

    /**
     * Limpiar logs antiguos
     */
    public function cleanupOldLogs(int $userId, int $daysToKeep = 30): int
    {
        return SyncLog::where('USER_ID', $userId)
                      ->where('CREATED_AT', '<', now()->subDays($daysToKeep))
                      ->delete();
    }

    /**
     * Generar nombre único para foto
     */
    private function generateFotoName(int $registroId, int $numeroBanco): string
    {
        return 'banco_' . $registroId . '_' . $numeroBanco . '_' . time() . '.jpg';
    }

    /**
     * Validar integridad de datos
     */
    public function validateDataIntegrity(array $data): array
    {
        $errors = [];

        // Validar estructura del registro
        if (!isset($data['patente_camion']) || !isset($data['id_chofer']) || !isset($data['id_transporte'])) {
            $errors[] = 'Faltan campos obligatorios del registro';
        }

        // Validar formato de patente
        if (isset($data['patente_camion']) && !preg_match('/^[A-Z]{2}[0-9]{4}$|^[A-Z]{4}[0-9]{2}$/', $data['patente_camion'])) {
            $errors[] = 'Formato de patente inválido';
        }

        // Validar detalles si existen
        if (isset($data['detalles'])) {
            foreach ($data['detalles'] as $index => $detalle) {
                if (!isset($detalle['numero_banco']) || $detalle['numero_banco'] < 1 || $detalle['numero_banco'] > 4) {
                    $errors[] = "Número de banco inválido en detalle $index";
                }
                
                if (!isset($detalle['diametro_cm']) || !in_array($detalle['diametro_cm'], TrozaDetail::getDiametrosDisponibles())) {
                    $errors[] = "Diámetro inválido en detalle $index";
                }
            }
        }

        return $errors;
    }
}