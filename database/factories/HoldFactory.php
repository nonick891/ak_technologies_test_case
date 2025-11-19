<?php

namespace Database\Factories;

use App\Models\Hold;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hold>
 */
class HoldFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Hold::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slot_id' => Slot::factory(),
            'status' => fake()->randomElement(['held', 'reserved', 'confirmed', 'released']),
            'idempotency_key' => fake()->uuid(),
            'expires_at' => now()->addMinutes(fake()->numberBetween(5, 60)),
        ];
    }
}
