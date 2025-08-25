<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrozaHead extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'ABAS_Troza_HEAD';
    protected $primaryKey = 'ID_REGISTRO';
    
    protected $fillable = [
        'PATENTE_CAMION', 'ID_CHOFER', 'ID_TRANSPORTE', 'FECHA_INICIO',
        'FECHA_CIERRE', 'ESTADO', 'USER_ID', 'OBSERVACIONES', 'TOTAL_TROZAS'
    ];

    protected $casts = [
        'FECHA_INICIO' => 'datetime',
        'FECHA_CIERRE' => 'datetime',
        'CREATED_AT' => 'datetime',
        'UPDATED_AT' => 'datetime',
        'TOTAL_TROZAS' => 'integer',
    ];

    // Relaciones
    public function chofer(): BelongsTo
    {
        return $this->belongsTo(Chofer::class, 'ID_CHOFER');
    }

    public function transporte(): BelongsTo
    {
        return $this->belongsTo(Transporte::class, 'ID_TRANSPORTE');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'USER_ID');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(TrozaDetail::class, 'ID_REGISTRO');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('ESTADO', '!=', 'CERRADO');
    }

    public function scopePorUsuario($query, $userId)
    {
        return $query->where('USER_ID', $userId);
    }

    public function scopeRecientes($query)
    {
        return $query->orderBy('FECHA_INICIO', 'desc');
    }

    // Accessors
    public function getTotalTrozasCalculadoAttribute()
    {
        return $this->detalles()->sum('CANTIDAD_TROZAS');
    }

    public function getBancosCerradosAttribute()
    {
        return $this->detalles()
            ->where('BANCO_CERRADO', true)
            ->distinct('NUMERO_BANCO')
            ->count('NUMERO_BANCO');
    }

    public function getBancosAbiertosAttribute()
    {
        $bancosCerrados = $this->bancos_cerrados;
        return 4 - $bancosCerrados;
    }

    // Métodos auxiliares
    public function puedeSerCerrado(): bool
    {
        return $this->ESTADO === 'ABIERTO' && $this->bancos_cerrados >= 1;
    }

    public function calcularTotalTrozas(): int
    {
        $total = $this->detalles()->sum('CANTIDAD_TROZAS');
        $this->update(['TOTAL_TROZAS' => $total]);
        return $total;
    }

	public function getResumenPorDiametro()
    {
        return $this->detalles()
            ->selectRaw('DIAMETRO_CM, SUM(CANTIDAD_TROZAS) as total')
            ->groupBy('DIAMETRO_CM')
            ->orderBy('DIAMETRO_CM')
            ->get();
    }
}