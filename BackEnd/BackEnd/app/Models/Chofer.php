<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Chofer extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'CHOFERES_PACK';
    protected $primaryKey = 'ID_CHOFER';
    
    protected $fillable = [
        'RUT_CHOFER',
        'NOMBRE_CHOFER', 
        'TELEFONO',
        'ID_TRANSPORTE',
        'VIGENCIA',
        'DATECREATE',
        'DATEUPDATE'
    ];

    protected $casts = [
        'DATECREATE' => 'datetime',
        'DATEUPDATE' => 'datetime',
        'TELEFONO' => 'integer',
        'VIGENCIA' => 'integer',
        'ID_TRANSPORTE' => 'integer'
    ];

    public $timestamps = false; // Usa DATECREATE y DATEUPDATE

    // ===================================
    // RELACIONES
    // ===================================

    /**
     * Relación con Transporte
     */
    public function transporte(): BelongsTo
    {
        return $this->belongsTo(Transporte::class, 'ID_TRANSPORTE', 'ID_TRANSPORTE');
    }

    /**
     * Relación con registros de trozas
     */
    public function registros(): HasMany
    {
        return $this->hasMany(TrozaHead::class, 'ID_CHOFER', 'ID_CHOFER');
    }

    /**
     * Relación con registros activos (no cerrados)
     */
    public function registrosActivos(): HasMany
    {
        return $this->hasMany(TrozaHead::class, 'ID_CHOFER', 'ID_CHOFER')
                    ->where('ESTADO', '!=', 'CERRADO');
    }

    /**
     * Relación con registros cerrados
     */
    public function registrosCerrados(): HasMany
    {
        return $this->hasMany(TrozaHead::class, 'ID_CHOFER', 'ID_CHOFER')
                    ->where('ESTADO', 'CERRADO');
    }

    // ===================================
    // SCOPES
    // ===================================

    /**
     * Scope para choferes activos/vigentes
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('VIGENCIA', 1);
    }

    /**
     * Scope para choferes inactivos
     */
    public function scopeInactivos(Builder $query): Builder
    {
        return $query->where('VIGENCIA', 0);
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopePorNombre(Builder $query, string $nombre): Builder
    {
        return $query->where('NOMBRE_CHOFER', 'like', "%{$nombre}%");
    }

    /**
     * Scope para buscar por RUT
     */
    public function scopePorRut(Builder $query, string $rut): Builder
    {
        $rut = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        return $query->where('RUT_CHOFER', 'like', "%{$rut}%");
    }

    /**
     * Scope para filtrar por transporte
     */
    public function scopePorTransporte(Builder $query, int $transporteId): Builder
    {
        return $query->where('ID_TRANSPORTE', $transporteId);
    }

    /**
     * Scope para choferes con registros
     */
    public function scopeConRegistros(Builder $query): Builder
    {
        return $query->whereHas('registros');
    }

    /**
     * Scope para choferes sin registros
     */
    public function scopeSinRegistros(Builder $query): Builder
    {
        return $query->whereDoesntHave('registros');
    }

    /**
     * Scope para choferes activos en un período
     */
    public function scopeActivosEnPeriodo(Builder $query, $fechaInicio, $fechaFin): Builder
    {
        return $query->whereHas('registros', function($subQuery) use ($fechaInicio, $fechaFin) {
            $subQuery->whereBetween('FECHA_INICIO', [$fechaInicio, $fechaFin]);
        });
    }

    /**
     * Scope para ordenar por nombre
     */
    public function scopeOrdenadoPorNombre(Builder $query, string $direccion = 'asc'): Builder
    {
        return $query->orderBy('NOMBRE_CHOFER', $direccion);
    }

    /**
     * Scope para ordenar por fecha de creación
     */
    public function scopeOrdenadoPorFecha(Builder $query, string $direccion = 'desc'): Builder
    {
        return $query->orderBy('DATECREATE', $direccion);
    }

    // ===================================
    // ACCESSORS
    // ===================================

    /**
     * Accessor para RUT formateado
     */
    public function getRutFormateadoAttribute(): string
    {
        return $this->formatearRut($this->RUT_CHOFER);
    }

    /**
     * Accessor para teléfono formateado
     */
    public function getTelefonoFormateadoAttribute(): ?string
    {
        return $this->formatearTelefono($this->TELEFONO);
    }

    /**
     * Accessor para nombre completo (incluye empresa)
     */
    public function getNombreCompletoAttribute(): string
    {
        $nombre = $this->NOMBRE_CHOFER;
        if ($this->transporte) {
            $nombre .= " ({$this->transporte->NOMBRE_TRANSPORTES})";
        }
        return $nombre;
    }

    /**
     * Accessor para estado vigencia en texto
     */
    public function getEstadoTextoAttribute(): string
    {
        return $this->VIGENCIA == 1 ? 'Activo' : 'Inactivo';
    }

    /**
     * Accessor para total de registros
     */
    public function getTotalRegistrosAttribute(): int
    {
        return $this->registros()->count();
    }

    /**
     * Accessor para total de registros cerrados
     */
    public function getTotalRegistrosCerradosAttribute(): int
    {
        return $this->registrosCerrados()->count();
    }

    /**
     * Accessor para total de trozas
     */
    public function getTotalTrozasAttribute(): int
    {
        return $this->registrosCerrados()->sum('TOTAL_TROZAS') ?? 0;
    }

    /**
     * Accessor para último registro
     */
    public function getUltimoRegistroAttribute(): ?TrozaHead
    {
        return $this->registros()->orderBy('FECHA_INICIO', 'desc')->first();
    }

    /**
     * Accessor para registros del mes actual
     */
    public function getRegistrosMesActualAttribute(): int
    {
        return $this->registros()
                    ->whereMonth('FECHA_INICIO', now()->month)
                    ->whereYear('FECHA_INICIO', now()->year)
                    ->count();
    }

    /**
     * Accessor para promedio de trozas por registro
     */
    public function getPromedioTrozasPorRegistroAttribute(): float
    {
        $registrosCerrados = $this->registrosCerrados()
                                  ->whereNotNull('TOTAL_TROZAS')
                                  ->get();
        
        if ($registrosCerrados->isEmpty()) {
            return 0;
        }
        
        return round($registrosCerrados->avg('TOTAL_TROZAS'), 2);
    }

    // ===================================
    // MUTATORS
    // ===================================

    /**
     * Mutator para RUT (limpiar formato antes de guardar)
     */
    public function setRutChoferAttribute($value): void
    {
        $this->attributes['RUT_CHOFER'] = $this->limpiarRut($value);
    }

    /**
     * Mutator para nombre (capitalizar)
     */
    public function setNombreChoferAttribute($value): void
    {
        $this->attributes['NOMBRE_CHOFER'] = mb_strtoupper(trim($value), 'UTF-8');
    }

    /**
     * Mutator para teléfono (solo números)
     */
    public function setTelefonoAttribute($value): void
    {
        $telefono = preg_replace('/[^0-9]/', '', $value);
        $this->attributes['TELEFONO'] = !empty($telefono) ? (int)$telefono : null;
    }

    // ===================================
    // MÉTODOS PÚBLICOS
    // ===================================

    /**
     * Verificar si el chofer está activo
     */
    public function estaActivo(): bool
    {
        return $this->VIGENCIA == 1;
    }

    /**
     * Verificar si el chofer tiene registros
     */
    public function tieneRegistros(): bool
    {
        return $this->registros()->exists();
    }

    /**
     * Activar chofer
     */
    public function activar(): bool
    {
        $this->VIGENCIA = 1;
        $this->DATEUPDATE = now();
        return $this->save();
    }

    /**
     * Desactivar chofer
     */
    public function desactivar(): bool
    {
        $this->VIGENCIA = 0;
        $this->DATEUPDATE = now();
        return $this->save();
    }

    /**
     * Obtener estadísticas del chofer
     */
    public function getEstadisticas(): array
    {
        return [
            'total_registros' => $this->total_registros,
            'registros_cerrados' => $this->total_registros_cerrados,
            'total_trozas' => $this->total_trozas,
            'registros_mes_actual' => $this->registros_mes_actual,
            'promedio_trozas_por_registro' => $this->promedio_trozas_por_registro,
            'ultimo_registro' => $this->ultimo_registro?->FECHA_INICIO,
            'dias_desde_ultimo_registro' => $this->ultimo_registro ? 
                now()->diffInDays($this->ultimo_registro->FECHA_INICIO) : null,
        ];
    }

    /**
     * Obtener registros recientes
     */
    public function getRegistrosRecientes(int $limite = 10): \Illuminate\Database\Eloquent\Collection
    {
        return $this->registros()
                    ->with(['detalles'])
                    ->orderBy('FECHA_INICIO', 'desc')
                    ->limit($limite)
                    ->get();
    }

    /**
     * Verificar si puede ser eliminado
     */
    public function puedeSerEliminado(): bool
    {
        // No se puede eliminar si tiene registros
        return !$this->tieneRegistros();
    }

    /**
     * Validar RUT
     */
    public function validarRut(): bool
    {
        return $this->esRutValido($this->RUT_CHOFER);
    }

    // ===================================
    // MÉTODOS ESTÁTICOS
    // ===================================

    /**
     * Buscar chofer por RUT
     */
    public static function buscarPorRut(string $rut): ?self
    {
        $rutLimpio = static::limpiarRutStatic($rut);
        return static::where('RUT_CHOFER', $rutLimpio)->activos()->first();
    }

    /**
     * Buscar choferes por nombre
     */
    public static function buscarPorNombre(string $nombre): \Illuminate\Database\Eloquent\Collection
    {
        return static::porNombre($nombre)->activos()->ordenadoPorNombre()->get();
    }

    /**
     * Obtener choferes de un transporte
     */
    public static function deTransporte(int $transporteId): \Illuminate\Database\Eloquent\Collection
    {
        return static::porTransporte($transporteId)->activos()->ordenadoPorNombre()->get();
    }

    /**
     * Obtener choferes más activos
     */
    public static function masActivos(int $limite = 10): \Illuminate\Database\Eloquent\Collection
    {
        return static::activos()
                     ->withCount('registros')
                     ->orderBy('registros_count', 'desc')
                     ->limit($limite)
                     ->get();
    }

    // ===================================
    // MÉTODOS PRIVADOS/AUXILIARES
    // ===================================

    /**
     * Formatear RUT chileno
     */
    private function formatearRut(?string $rut): string
    {
        if (empty($rut)) return '';
        
        $rut = preg_replace('/[^0-9kK]/', '', strtoupper($rut));
        
        if (strlen($rut) < 8) {
            return $rut;
        }
        
        $dv = substr($rut, -1);
        $number = substr($rut, 0, -1);
        
        return number_format($number, 0, '', '.') . '-' . $dv;
    }

    /**
     * Formatear teléfono
     */
    private function formatearTelefono(?int $telefono): ?string
    {
        if (empty($telefono)) return null;
        
        $telefonoStr = (string)$telefono;
        
        if (strlen($telefonoStr) == 9 && substr($telefonoStr, 0, 1) == '9') {
            // Celular: +56 9 XXXX XXXX
            return '+56 ' . substr($telefonoStr, 0, 1) . ' ' . 
                   substr($telefonoStr, 1, 4) . ' ' . substr($telefonoStr, 5);
        } elseif (strlen($telefonoStr) == 8) {
            // Fijo: +56 XX XXX XXXX
            return '+56 ' . substr($telefonoStr, 0, 2) . ' ' . 
                   substr($telefonoStr, 2, 3) . ' ' . substr($telefonoStr, 5);
        }
        
        return $telefonoStr;
    }

    /**
     * Limpiar RUT (quitar puntos y guión)
     */
    private function limpiarRut(string $rut): string
    {
        return static::limpiarRutStatic($rut);
    }

    /**
     * Limpiar RUT (método estático)
     */
    private static function limpiarRutStatic(string $rut): string
    {
        return preg_replace('/[^0-9kK]/', '', strtoupper(trim($rut)));
    }

    /**
     * Validar si el RUT es válido
     */
    private function esRutValido(string $rut): bool
    {
        $rut = $this->limpiarRut($rut);
        
        if (strlen($rut) < 8 || strlen($rut) > 9) {
            return false;
        }
        
        $dv = substr($rut, -1);
        $number = substr($rut, 0, -1);
        
        if (!is_numeric($number)) {
            return false;
        }
        
        return $dv === $this->calcularDV($number);
    }

    /**
     * Calcular dígito verificador del RUT
     */
    private function calcularDV(string $number): string
    {
        $factor = 2;
        $sum = 0;
        
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $sum += $number[$i] * $factor;
            $factor = $factor == 7 ? 2 : $factor + 1;
        }
        
        $dv = 11 - ($sum % 11);
        
        if ($dv == 11) return '0';
        if ($dv == 10) return 'K';
        
        return (string)$dv;
    }

    // ===================================
    // BOOT METHOD
    // ===================================

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Evento al crear
        static::creating(function ($chofer) {
            $chofer->DATECREATE = now();
            $chofer->VIGENCIA = $chofer->VIGENCIA ?? 1;
        });

        // Evento al actualizar
        static::updating(function ($chofer) {
            $chofer->DATEUPDATE = now();
        });
    }
}