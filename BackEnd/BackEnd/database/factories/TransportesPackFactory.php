<?php

namespace Database\Factories;

use App\Models\TransportesPack;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransportesPackFactory extends Factory
{
    protected $model = TransportesPack::class;

    public function definition()
    {
        return [
            'NOMBRE_TRANSPORTES' => $this->faker->company . ' Transportes',
            'VIGENCIA' => 1,
            'TRASLADO_BODEGAS' => $this->faker->boolean,
            'DATECREATE' => now(),
            'RUT' => $this->faker->randomNumber(8) . '-' . $this->faker->randomElement(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', 'K'])
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

    public function withBodegaTransfer()
    {
        return $this->state(function (array $attributes) {
            return [
                'TRASLADO_BODEGAS' => true,
            ];
        });
    }
}