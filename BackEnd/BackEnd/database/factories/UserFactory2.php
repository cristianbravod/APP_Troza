<?php

// database/factories/UserFactory.php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition()
    {
        return [
            'ip_address' => $this->faker->ipv4,
            'username' => $this->faker->unique()->userName,
            'password' => Hash::make('password'),
            'email' => $this->faker->unique()->safeEmail,
            'created_on' => time(),
            'active' => 1,
            'first_name' => $this->faker->firstName,
            'last_name' => $this->faker->lastName,
            'company' => $this->faker->company,
            'phone' => $this->faker->phoneNumber,
            'User_Activo' => true,
            'User_Web' => false,
            'User_Totem' => false,
            'User_App' => true,
            'User_Infoela' => false,
            'User_AppProd' => false,
            'User_Casino' => false,
            'User_Porteria' => false,
            'User_Language' => 'spanish'
        ];
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'active' => 0,
                'User_Activo' => false,
            ];
        });
    }

    public function withoutAppAccess()
    {
        return $this->state(function (array $attributes) {
            return [
                'User_App' => false,
            ];
        });
    }

    public function admin()
    {
        return $this->state(function (array $attributes) {
            return [
                'User_Web' => true,
                'User_App' => true,
            ];
        });
    }
}