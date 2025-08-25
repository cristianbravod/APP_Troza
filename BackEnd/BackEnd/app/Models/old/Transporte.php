<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transporte extends Model
{
    protected $connection = 'sqlsrv'; // Conexión a Producto_Terminado
    protected $table = 'TRANSPORTES_PACK';
    protected $primaryKey = 'ID_TRANSPORTE';
    
    protected $fillable = [
        'NOMBRE_TRANSPORTES', 'VIGENCIA', 'TRASLADO_BODEGAS', 'RUT'
    ];

    protected $casts = [
        'TRASLADO_BODEGAS' => 'boolean',
        'DATECREATE' => 'datetime'
    ];

    public $timestamps = false; // Usa DATECREATE

    public function choferes(): HasMany
    {
        return $this->hasMany(Chofer::class, 'ID_TRANSPORTE');
    }

    public function registrosTroza(): HasMany
    {
        return $this->hasMany(TrozaHead::class, 'ID_TRANSPORTE');
    }

    // Scope para transportes activos
    public function scopeActive($query)
    {
        return $query->where('VIGENCIA', 1);
    }
}
