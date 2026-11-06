<?php

namespace Database\Factories;

use App\Enums\AccessMode;
use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Event> */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Mariage de '.fake()->firstName().' & '.fake()->firstName(),
            'event_date' => now()->subDay(),
            'location' => fake()->city(),
            'access_mode' => AccessMode::Gallery,
            'status' => EventStatus::Published,
            'watermark_enabled' => true,
            'has_minors' => false,
            'retention_days' => 60,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => EventStatus::Draft]);
    }
}
