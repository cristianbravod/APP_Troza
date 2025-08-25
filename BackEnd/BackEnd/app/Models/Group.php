<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $connection = 'evaluacion';
    protected $table = 'groups_modulos';
    public $timestamps = false;
    
    protected $fillable = ['group_id', 'module_id'];
    
    // No tiene primary key propia, es tabla pivot
    public $incrementing = false;
    protected $primaryKey = null;
}