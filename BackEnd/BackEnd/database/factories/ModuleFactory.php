<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition()
    {
        return [
            'NAME' => $this->faker->unique()->word,
            'DESCRIPTION' => $this->faker->sentence,
            'DEPENDENCY' => 0,
            'PRIORITY' => $this->faker->numberBetween(1, 10),
            'URL' => '/' . $this->faker->slug,
            'ICON' => 'fa-' . $this->faker->word,
            'VIGENCIA' => true
        ];
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'VIGENCIA' => false,
            ];
        });
    }

    public function withDependency($dependencyId)
    {
        return $this->state(function (array $attributes) use ($dependencyId) {
            return [
                'DEPENDENCY' => $dependencyId,
            ];
        });
    }
}