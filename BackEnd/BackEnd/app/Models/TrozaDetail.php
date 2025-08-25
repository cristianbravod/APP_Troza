<?php
// app/Models/TrozaDetail.php - VERSIÓN 2.0 CON LARGOS

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrozaDetail extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'ABAS_Troza_DETAIL';
    protected $primaryKey = 'ID_DETALLE';
    
    protected $fillable = [
        'ID_REGISTRO', 'NUMERO_BANCO', 'DIAMETRO_CM', 'LARGO_M', 'CANTIDAD_TROZAS',
        'FECHA_CIERRE_BANCO', 'FOTO_PATH', 'GPS_LATITUD', 'GPS_LONGITUD', 
        'GPS_ACCURACY', 'BANCO_CERRADO', 'OBSERVACIONES_BANCO'
    ];

    protected $casts = [
        'FECHA_CIERRE_BANCO' => 'datetime',
        'GPS_LATITUD' => 'decimal:8',
        'GPS_LONGITUD' => 'decimal:8',
        'GPS_ACCURACY' => 'decimal:2',
        'LARGO_M' => 'decimal:2',
        'BANCO_CERRADO' => 'boolean',
        'CREATED_AT' => 'datetime',
        'CANTIDAD_TROZAS' => 'integer',
        'NUMERO_BANCO' => 'integer',
        'DIAMETRO_CM' => 'integer',
    ];

    public $timestamps = false; // Solo usa CREATED_AT

    // ===============================
    // RELACIONES
    // ===============================

    public function registro(): BelongsTo
    {
        return $this->belongsTo(TrozaHead::class, 'ID_REGISTRO', 'ID_REGISTRO');
    }

    // ===============================
    // SCOPES
    // ===============================

    public function scopePorBanco($query, $numeroBanco)
    {
        return $query->where('NUMERO_BANCO', $numeroBanco);
    }

    public function scopeBancosCerrados($query)
    {
        return $query->where('BANCO_CERRADO', true);
    }

    public function scopeBancosAbiertos($query)
    {
        return $query->where('BANCO_CERRADO', false);
    }

    public function scopePorDiametro($query, $diametro)
    {
        return $query->where('DIAMETRO_CM', $diametro);
    }

    public function scopePorLargo($query, $largo)
    {
        return $query->where('LARGO_M', $largo);
    }

    public function scopePorCombinacion($query, $diametro, $largo)
    {
        return $query->where('DIAMETRO_CM', $diametro)
                    ->where('LARGO_M', $largo);
    }

    public function scopeConTrozas($query)
    {
        return $query->where('CANTIDAD_TROZAS', '>', 0);
    }

    public function scopeConFoto($query)
    {
        return $query->whereNotNull('FOTO_PATH');
    }

    public function scopeConGPS($query)
    {
        return $query->whereNotNull('GPS_LATITUD')
                    ->whereNotNull('GPS_LONGITUD');
    }

    // ===============================
    // ACCESSORS
    // ===============================

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
            'longitude' => (float) $this->GPS_LONGITUD,
            'accuracy' => $this->GPS_ACCURACY ? (float) $this->GPS_ACCURACY : null
        ];
    }

    public function getCombinacionKeyAttribute()
    {
        return "{$this->DIAMETRO_CM}cm_x_{$this->LARGO_M}m";
    }

    public function getVolumenEstimadoAttribute()
    {
        // Cálculo aproximado de volumen: π * (radio²) * largo * cantidad
        // Radio = diámetro / 2 / 100 (convertir cm a metros)
        $radioM = ($this->DIAMETRO_CM / 2) / 100;
        $volumenUnitario = pi() * pow($radioM, 2) * $this->LARGO_M;
        return round($volumenUnitario * $this->CANTIDAD_TROZAS, 4);
    }

    // ===============================
    // MÉTODOS ESTÁTICOS
    // ===============================

    public static function getDiametrosDisponibles(): array
    {
        return [22, 24, 26, 28, 30, 32, 34, 36, 38, 40, 42, 44, 46, 48, 50, 52, 54, 56, 58, 60];
    }

    public static function getLargosDisponibles(): array
    {
        return [2.00, 2.50, 2.60, 3.80, 5.10];
    }

    public static function getCombinacionesDisponibles(): array
    {
        $combinaciones = [];
        foreach (self::getDiametrosDisponibles() as $diametro) {
            foreach (self::getLargosDisponibles() as $largo) {
                $combinaciones[] = [
                    'diametro_cm' => $diametro,
                    'largo_m' => $largo,
                    'key' => "{$diametro}cm_x_{$largo}m",
                    'descripcion' => "{$diametro}cm × {$largo}m"
                ];
            }
        }
        return $combinaciones;
    }

    public static function getEstadisticasPorDiametro($registroId = null)
    {
        $query = self::selectRaw('DIAMETRO_CM, SUM(CANTIDAD_TROZAS) as total_trozas, COUNT(*) as registros, SUM(CANTIDAD_TROZAS * LARGO_M) as metros_lineales_total')
                     ->groupBy('DIAMETRO_CM')
                     ->orderBy('DIAMETRO_CM');
        
        if ($registroId) {
            $query->where('ID_REGISTRO', $registroId);
        }
        
        return $query->get()->map(function($item) {
            return [
                'diametro_cm' => $item->DIAMETRO_CM,
                'total_trozas' => $item->total_trozas,
                'registros' => $item->registros,
                'metros_lineales_total' => round($item->metros_lineales_total, 2)
            ];
        });
    }

    public static function getEstadisticasPorLargo($registroId = null)
    {
        $query = self::selectRaw('LARGO_M, SUM(CANTIDAD_TROZAS) as total_trozas, COUNT(*) as registros')
                     ->groupBy('LARGO_M')
                     ->orderBy('LARGO_M');
        
        if ($registroId) {
            $query->where('ID_REGISTRO', $registroId);
        }
        
        return $query->get();
    }

    public static function getEstadisticasPorCombinacion($registroId = null)
    {
        $query = self::selectRaw('DIAMETRO_CM, LARGO_M, SUM(CANTIDAD_TROZAS) as total_trozas, COUNT(*) as registros')
                     ->groupBy('DIAMETRO_CM', 'LARGO_M')
                     ->orderBy('DIAMETRO_CM')
                     ->orderBy('LARGO_M');
        
        if ($registroId) {
            $query->where('ID_REGISTRO', $registroId);
        }
        
        return $query->get()->map(function($item) {
            return [
                'diametro_cm' => $item->DIAMETRO_CM,
                'largo_m' => $item->LARGO_M,
                'combinacion' => "{$item->DIAMETRO_CM}cm × {$item->LARGO_M}m",
                'total_trozas' => $item->total_trozas,
                'registros' => $item->registros,
                'volumen_estimado' => round(pi() * pow(($item->DIAMETRO_CM / 2 / 100), 2) * $item->LARGO_M * $item->total_trozas, 4)
            ];
        });
    }

    public static function getBancosResumen($registroId)
    {
        $bancos = [];
        for ($i = 1; $i <= 4; $i++) {
            $detalles = self::where('ID_REGISTRO', $registroId)
                           ->where('NUMERO_BANCO', $i)
                           ->get();
            
            $bancos[$i] = [
                'numero' => $i,
                'total_trozas' => $detalles->sum('CANTIDAD_TROZAS'),
                'total_combinaciones' => $detalles->count(),
                'cerrado' => $detalles->isNotEmpty() && $detalles->first()->BANCO_CERRADO,
                'fecha_cierre' => $detalles->isNotEmpty() ? $detalles->first()->FECHA_CIERRE_BANCO : null,
                'tiene_foto' => $detalles->isNotEmpty() && $detalles->first()->FOTO_PATH,
                'tiene_gps' => $detalles->isNotEmpty() && $detalles->first()->GPS_LATITUD && $detalles->first()->GPS_LONGITUD,
                'volumen_estimado' => $detalles->sum('volumen_estimado')
            ];
        }
        return $bancos;
    }

    // ===============================
    // VALIDACIONES PERSONALIZADAS
    // ===============================

    public static function validarDiametro($diametro): bool
    {
        return in_array($diametro, self::getDiametrosDisponibles());
    }

    public static function validarLargo($largo): bool
    {
        return in_array((float) $largo, self::getLargosDisponibles());
    }

    public static function validarCombinacion($diametro, $largo): bool
    {
        return self::validarDiametro($diametro) && self::validarLargo($largo);
    }

    public static function validarNumeroBanco($numero): bool
    {
        return $numero >= 1 && $numero <= 4;
    }

    public static function validarCantidad($cantidad): bool
    {
        return is_int($cantidad) && $cantidad >= 0 && $cantidad <= 999;
    }

    // ===============================
    // MÉTODOS DE UTILIDAD
    // ===============================

    public function esBancoCerrado(): bool
    {
        return (bool) $this->BANCO_CERRADO;
    }

    public function tieneFoto(): bool
    {
        return !empty($this->FOTO_PATH);
    }

    public function tieneGPS(): bool
    {
        return !empty($this->GPS_LATITUD) && !empty($this->GPS_LONGITUD);
    }

    public function getDiasDesdeCreacion(): int
    {
        if (!$this->CREATED_AT) return 0;
        return $this->CREATED_AT->diffInDays(now());
    }

    public function getDiasDesdecierre(): ?int
    {
        if (!$this->FECHA_CIERRE_BANCO) return null;
        return $this->FECHA_CIERRE_BANCO->diffInDays(now());
    }
}