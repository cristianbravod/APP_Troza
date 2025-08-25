<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrozaDetail extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'ABAS_Troza_DETAIL';
    protected $primaryKey = 'ID_DETALLE';
    
    protected $fillable = [
        'ID_REGISTRO', 'NUMERO_BANCO', 'DIAMETRO_CM', 'CANTIDAD_TROZAS',
        'FECHA_CIERRE_BANCO', 'FOTO_PATH', 'GPS_LATITUD', 'GPS_LONGITUD', 'BANCO_CERRADO'
    ];

    protected $casts = [
        'FECHA_CIERRE_BANCO' => 'datetime',
        'GPS_LATITUD' => 'decimal:8',
        'GPS_LONGITUD' => 'decimal:8',
        'BANCO_CERRADO' => 'boolean',
        'CREATED_AT' => 'datetime',
        'CANTIDAD_TROZAS' => 'integer',
        'NUMERO_BANCO' => 'integer',
        'DIAMETRO_CM' => 'integer',
    ];

    public $timestamps = false; // Solo usa CREATED_AT

    // Relaciones
    public function registro(): BelongsTo
    {
        return $this->belongsTo(TrozaHead::class, 'ID_REGISTRO');
    }

    // Scopes
    public function scopePorBanco($query, $numeroBanco)
    {
        return $query->where('NUMERO_BANCO', $numeroBanco);
    }

    public function scopeBancosCerrados($query)
    {
        return $query->where('BANCO_CERRADO', true);
    }

    public function scopePorDiametro($query, $diametro)
    {
        return $query->where('DIAMETRO_CM', $diametro);
    }

    public function scopeConTrozas($query)
    {
        return $query->where('CANTIDAD_TROZAS', '>', 0);
    }

    // Accessors
    public function getFotoUrlAttribute()
    {
        if (!$this->FOTO_PATH) return null;
        
        // Si es una URL completa, devolverla tal como está
        if (filter_var($this->FOTO_PATH, FILTER_VALIDATE_URL)) {
            return $this->FOTO_PATH;
        }
        
        // Si es un path relativo, construir la URL completa
        return asset('storage/' . $this->FOTO_PATH);
    }

    public function getGpsCoordsAttribute()
    {
        if (!$this->GPS_LATITUD || !$this->GPS_LONGITUD) return null;
        
        return [
            'latitude' => (float) $this->GPS_LATITUD,
            'longitude' => (float) $this->GPS_LONGITUD
        ];
    }

    // Métodos estáticos
    public static function getDiametrosDisponibles(): array
    {
        $diametros = [];
        for ($i = 22; $i <= 60; $i += 2) {
            $diametros[] = $i;
        }
        return $diametros;
    }

    public static function getEstadisticasPorDiametro($registroId = null)
    {
        $query = self::selectRaw('DIAMETRO_CM, SUM(CANTIDAD_TROZAS) as total_trozas, COUNT(*) as registros')
                     ->groupBy('DIAMETRO_CM')
                     ->orderBy('DIAMETRO_CM');
        
        if ($registroId) {
            $query->where('ID_REGISTRO', $registroId);
        }
        
        return $query->get();
    }

    // Validaciones personalizadas
    public static function validarDiametro($diametro): bool
    {
        return in_array($diametro, self::getDiametrosDisponibles());
    }

    public static function validarNumeroBanco($numero): bool
    {
        return $numero >= 1 && $numero <= 4;
    }
}