<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SyncLog extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'SYNC_LOG';
    protected $primaryKey = 'ID_SYNC';
    
    protected $fillable = [
        'USER_ID', 'DEVICE_ID', 'SYNC_TYPE', 'ENTITY_TYPE',
        'ENTITY_ID', 'SYNC_STATUS', 'ERROR_MESSAGE', 'PROCESSED_AT'
    ];

    protected $casts = [
        'CREATED_AT' => 'datetime',
        'PROCESSED_AT' => 'datetime',
        'ENTITY_ID' => 'integer',
        'USER_ID' => 'integer',
    ];

    public $timestamps = false;

    // Constantes para tipos de sincronización
    const SYNC_TYPE_UPLOAD = 'UPLOAD';
    const SYNC_TYPE_DOWNLOAD = 'DOWNLOAD';
    
    const ENTITY_TYPE_REGISTRO = 'REGISTRO';
    const ENTITY_TYPE_FOTO = 'FOTO';
    
    const STATUS_PENDING = 'PENDING';
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_ERROR = 'ERROR';

    // Relaciones
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'USER_ID');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('SYNC_STATUS', self::STATUS_PENDING);
    }

    public function scopePorUsuario($query, $userId)
    {
        return $query->where('USER_ID', $userId);
    }

    public function scopePorTipo($query, $syncType)
    {
        return $query->where('SYNC_TYPE', $syncType);
    }

    public function scopeRecientes($query, $hours = 24)
    {
        return $query->where('CREATED_AT', '>=', now()->subHours($hours));
    }

    // Métodos estáticos para crear logs
    public static function logUpload($userId, $deviceId, $entityType, $entityId, $status = self::STATUS_PENDING, $errorMessage = null)
    {
        return self::create([
            'USER_ID' => $userId,
            'DEVICE_ID' => $deviceId,
            'SYNC_TYPE' => self::SYNC_TYPE_UPLOAD,
            'ENTITY_TYPE' => $entityType,
            'ENTITY_ID' => $entityId,
            'SYNC_STATUS' => $status,
            'ERROR_MESSAGE' => $errorMessage,
            'CREATED_AT' => now(),
            'PROCESSED_AT' => $status !== self::STATUS_PENDING ? now() : null,
        ]);
    }

    public static function logSuccess($syncLogId)
    {
        return self::where('ID_SYNC', $syncLogId)->update([
            'SYNC_STATUS' => self::STATUS_SUCCESS,
            'PROCESSED_AT' => now(),
            'ERROR_MESSAGE' => null,
        ]);
    }

    public static function logError($syncLogId, $errorMessage)
    {
        return self::where('ID_SYNC', $syncLogId)->update([
            'SYNC_STATUS' => self::STATUS_ERROR,
            'PROCESSED_AT' => now(),
            'ERROR_MESSAGE' => $errorMessage,
        ]);
    }

    // Métodos para estadísticas
    public static function getEstadisticasSync($userId = null, $days = 7)
    {
        $query = self::selectRaw('
            SYNC_STATUS,
            COUNT(*) as total,
            COUNT(CASE WHEN ENTITY_TYPE = ? THEN 1 END) as registros,
            COUNT(CASE WHEN ENTITY_TYPE = ? THEN 1 END) as fotos
        ', [self::ENTITY_TYPE_REGISTRO, self::ENTITY_TYPE_FOTO])
        ->where('CREATED_AT', '>=', now()->subDays($days))
        ->groupBy('SYNC_STATUS');

        if ($userId) {
            $query->where('USER_ID', $userId);
        }

        return $query->get();
    }
}