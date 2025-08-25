<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition()
    {
        return [
            'name' => $this->faker->unique()->word,
            'description' => $this->faker->sentence,
            'status' => 1,
            'AppProd' => false
        ];
    }

    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 0,
            ];
        });
    }

    public function appProd()
    {
        return $this->state(function (array $attributes) {
            return [
                'AppProd' => true,
            ];
        });
    }
