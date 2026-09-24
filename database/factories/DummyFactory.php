<?php

namespace Database\Factories;

use App\Enums\DummyCategory;
use App\Models\Dummy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dummy>
 */
class DummyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'category' => fake()->randomElement(DummyCategory::cases()),
            'description' => fake()->paragraph(),
        ];
    }
}
