<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    protected $connection = 'evaluacion';
    protected $table = 'MODULOS';
    public $timestamps = false;

    protected $fillable = [
        'NAME', 'DESCRIPTION', 'DEPENDENCY', 'PRIORITY', 'URL', 'ICON', 'VIGENCIA'
    ];

    protected $casts = [
        'VIGENCIA' => 'boolean',
        'PRIORITY' => 'integer',
        'DEPENDENCY' => 'integer',
    ];

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'groups_modulos', 'module_id', 'group_id');
    }

    // Scope para módulos activos
    public function scopeActive($query)
    {
        return $query->where('VIGENCIA', true);
    }

    // Scope ordenado por prioridad
    public function scopeOrdered($query)
    {
        return $query->orderBy('PRIORITY')->orderBy('NAME');
    }
}