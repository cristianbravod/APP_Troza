<?php

namespace Database\Factories;

use App\Models\ChoferesPack;
use App\Models\TransportesPack;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChoferesPackFactory extends Factory
{
    protected $model = ChoferesPack::class;

    public function definition()
    {
        return [
            'RUT_CHOFER' => $this->faker->randomNumber(8) . '-' . $this->faker->randomElement(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'K']),
            'NOMBRE_CHOFER' => $this->faker->firstName . ' ' . $this->faker->lastName,
            'TELEFONO' => $this->faker->numberBetween(900000000, 999999999),
            'ID_TRANSPORTE' => TransportesPack::factory(),
            'VIGENCIA' => 1,
            'DATECREATE' => now(),
            'DATEUPDATE' => null
        ];
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'VIGENCIA' => 0,
            ];
        });
    }

    public function forTransport($transportId)
    {
        return $this->state(function (array $attributes) use ($transportId) {
            return [
                'ID_TRANSPORTE' => $transportId,
            ];
        });
    }
}