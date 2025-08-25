<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chofer extends Model
{
    protected $connection = 'sqlsrv';
    protected $table = 'CHOFERES_PACK';
    protected $primaryKey = 'ID_CHOFER';
    
    protected $fillable = [
        'RUT_CHOFER', 'NOMBRE_CHOFER', 'TELEFONO', 'ID_TRANSPORTE', 'VIGENCIA'
    ];

    protected $casts = [
        'DATECREATE' => 'datetime',
        'DATEUPDATE' => 'datetime'
    ];

    public $timestamps = false;

    public function transporte(): BelongsTo
    {
        return $this->belongsTo(Transporte::class, 'ID_TRANSPORTE');
    }

    public function registrosTroza(): HasMany
    {
        return $this->hasMany(TrozaHead::class, 'ID_CHOFER');
    }

    // Scope para choferes activos
    public function scopeActive($query)
    {
        return $query->where('VIGENCIA', 1);
    }

    // Accessor para formatear RUT
    public function getFormattedRutAttribute()
    {
        $rut = $this->RUT_CHOFER;
        if (strlen($rut) >= 2) {
            return substr($rut, 0, -1) . '-' . substr($rut, -1);
        }
        return $rut;
    }
}
