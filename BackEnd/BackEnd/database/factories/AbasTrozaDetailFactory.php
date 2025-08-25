<?php
namespace Database\Factories;

use App\Models\AbasTrozaDetail;
use App\Models\AbasTrozaHead;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbasTrozaDetailFactory extends Factory
{
    protected $model = AbasTrozaDetail::class;

    public function definition()
    {
        return [
            'head_id' => AbasTrozaHead::factory(),
            'numero_banco' => $this->faker->numberBetween(1, 4),
            'diametro' => $this->faker->randomElement($this->getValidDiameters()),
            'cantidad' => $this->faker->numberBetween(1, 50),
            'estado' => $this->faker->randomElement(['ABIERTO', 'CERRADO']),
            'foto_path' => null,
            'ubicacion_gps' => null,
            'fecha_cierre' => null,
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    public function open()
    {
        return $this->state(function (array $attributes) {
            return [
                'estado' => 'ABIERTO',
                'foto_path' => null,
                'fecha_cierre' => null,
            ];
        });
    }

    public function closed()
    {
        return $this->state(function (array $attributes) {
            return [
                'estado' => 'CERRADO',
                'foto_path' => 'banks/' . $this->faker->uuid . '.jpg',
                'ubicacion_gps' => $this->faker->latitude . ', ' . $this->faker->longitude,
                'fecha_cierre' => now(),
            ];
        });
    }

    public function forBank($bankNumber)
    {
        return $this->state(function (array $attributes) use ($bankNumber) {
            return [
                'numero_banco' => $bankNumber,
            ];
        });
    }

    public function withDiameter($diameter)
    {
        return $this->state(function (array $attributes) use ($diameter) {
            return [
                'diametro' => $diameter,
            ];
        });
    }

    private function getValidDiameters()
    {
        // Diámetros válidos: 22 a 60, solo números pares
        $diameters = [];
        for ($i = 22; $i <= 60; $i += 2) {
            $diameters[] = $i;
        }
        return $diameters;
    }
}