<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function verifyCredentials($username, $password)
    {
        $user = User::where(function($query) use ($username) {
                        $query->where('username', $username)
                              ->orWhere('email', $username);
                    })
                    ->where('User_Activo', true)
                    ->where('User_AppProd', true)
                    ->first();

        if (!$user) {
            return null;
        }

        // Verificar contraseña según el sistema existente
        if ($this->verifyPassword($password, $user->password, $user->salt)) {
            return $user;
        }

        return null;
    }

    private function verifyPassword($plainPassword, $hashedPassword, $salt = null)
    {
        // Compatibilidad con sistema existente de CodeIgniter
        if ($salt) {
            return $hashedPassword === sha1($salt . sha1($plainPassword));
        }
        
        // Para nuevos usuarios con Laravel Hash
        return Hash::check($plainPassword, $hashedPassword);
    }

    public function getUserPermissions(User $user)
    {
        $user->load(['groups.modules']);
        
        return [
            'groups' => $user->groups->pluck('name')->toArray(),
            'modules' => $user->groups->flatMap->modules->unique('id')->pluck('NAME')->toArray()
        ];
    }
}