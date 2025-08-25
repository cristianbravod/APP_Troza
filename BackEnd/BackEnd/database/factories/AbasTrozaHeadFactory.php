<?php


namespace Database\Factories;

use App\Models\AbasTrozaHead;
use App\Models\User;
use App\Models\TransportesPack;
use App\Models\ChoferesPack;
use Illuminate\Database\Eloquent\Factories\Factory;

class AbasTrozaHeadFactory extends Factory
{
    protected $model = AbasTrozaHead::class;

    public function definition()
    {
        $transporte = TransportesPack::factory()->create();
        $chofer = ChoferesPack::factory()->create(['ID_TRANSPORTE' => $transporte->ID_TRANSPORTE]);

        return [
            'patente' => $this->generatePatente(),
            'ID_TRANSPORTE' => $transporte->ID_TRANSPORTE,
            'ID_CHOFER' => $chofer->ID_CHOFER,
            'fecha_carga' => $this->faker->date(),
            'ubicacion_gps' => $this->faker->latitude . ', ' . $this->faker->longitude,
            'observaciones' => $this->faker->optional()->sentence,
            'estado' => $this->faker->randomElement(['ABIERTO', 'COMPLETADO']),
            'user_id' => User::factory(),
            'created_at' => now(),
            'updated_at' => now(),
            'synced' => $this->faker->boolean(70), // 70% probabilidad de estar sincronizado
            'temp_id' => null
        ];
    }

    public function open()
    {
        return $this->state(function (array $attributes) {
            return [
                'estado' => 'ABIERTO',
            ];
        });
    }

    public function completed()
    {
        return $this->state(function (array $attributes) {
            return [
                'estado' => 'COMPLETADO',
            ];
        });
    }

    public function unsynced()
    {
        return $this->state(function (array $attributes) {
            return [
                'synced' => false,
            ];
        });
    }

    public function withTempId()
    {
        return $this->state(function (array $attributes) {
            return [
                'temp_id' => 'temp_' . $this->faker->uuid,
            ];
        });
    }

    private function generatePatente()
    {
        $patterns = [
            // Patrón chileno: 4 letras + 2 números
            $this->faker->lexify('????') . $this->faker->numerify('##'),
            // Patrón antiguo: 2 letras + 4 números
            $this->faker->lexify('??') . $this->faker->numerify('####'),
            // Patrón nuevo: 2 letras + 2 números + 2 letras
            $this->faker->lexify('??') . $this->faker->numerify('##') . $this->faker->lexify('??')
        ];

        return strtoupper($this->faker->randomElement($patterns));
    }
}