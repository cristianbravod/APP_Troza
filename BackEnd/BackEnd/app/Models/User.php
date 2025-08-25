<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $connection = 'evaluacion'; // Conexión a base de datos Evaluacion
    protected $table = 'users';
    
    protected $fillable = [
        'username', 'email', 'password', 'first_name', 'last_name',
        'company', 'phone', 'User_Activo', 'User_AppProd'
    ];

    protected $hidden = ['password', 'salt'];

    protected $casts = [
        'User_Activo' => 'boolean',
        'User_Web' => 'boolean',
        'User_Totem' => 'boolean',
        'User_App' => 'boolean',
        'User_AppProd' => 'boolean',
        'User_Casino' => 'boolean',
        'User_Porteria' => 'boolean',
        'User_Infoela' => 'boolean',
        'created_on' => 'timestamp',
        'last_login' => 'timestamp',
    ];

    public $timestamps = false; // La tabla usa created_on en lugar de created_at

    // Relaciones
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'users_groups', 'user_id', 'group_id');
    }

    public function modules()
    {
        return $this->hasManyThrough(
            Module::class,
            'App\Models\GroupModule',
            'group_id',
            'id',
            'id',
            'module_id'
        )->join('users_groups', 'users_groups.group_id', '=', 'groups_modulos.group_id')
         ->where('users_groups.user_id', $this->id);
    }

    public function trozaRegistros(): HasMany
    {
        return $this->hasMany(TrozaHead::class, 'USER_ID');
    }

    // JWT Methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'username' => $this->username,
            'groups' => $this->groups->pluck('name'),
            'modules' => $this->getModuleNames()
        ];
    }

    public function hasModuleAccess($moduleName): bool
    {
        return $this->getModuleNames()->contains($moduleName);
    }

    public function getModuleNames()
    {
        return $this->groups->flatMap->modules->unique('id')->pluck('NAME');
    }

    // Verificar contraseña compatible con sistema existente
    public function verifyPassword($plainPassword): bool
    {
        // Sistema existente usa salt + sha1
        if ($this->salt) {
            return $this->password === sha1($this->salt . sha1($plainPassword));
        }
        
        // Para nuevos usuarios con Hash de Laravel
        return password_verify($plainPassword, $this->password);
    }
}