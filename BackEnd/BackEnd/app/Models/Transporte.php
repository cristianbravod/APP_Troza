<?php
// app/Models/Transporte.php

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

    // Scope para transportes activos
    public function scopeActive($query)
    {
        return $query->where('VIGENCIA', 1);
    }
}

// app/Models/Chofer.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

// app/Models/Group.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Group extends Model
{
    protected $connection = 'evaluacion';
    protected $table = 'groups';
    public $timestamps = false;

    protected $fillable = ['name', 'description', 'status', 'AppProd'];

    protected $casts = [
        'AppProd' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'users_groups', 'group_id', 'user_id');
    }

    // Scope para grupos activos
    public function scopeActive($query)
    {
        return $query->where('status', 1);
    }

    // Scope para grupos de aplicación
    public function scopeAppProd($query)
    {
        return $query->where('AppProd', true);
    }
}