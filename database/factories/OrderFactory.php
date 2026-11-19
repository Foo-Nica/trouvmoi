<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Order> */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'customer_name' => fake()->name(),
            'customer_phone' => '01'.fake()->numerify('########'),
            'customer_email' => fake()->safeEmail(),
            'amount' => 2000,
            'status' => OrderStatus::Pending,
            'gateway' => 'fake',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => OrderStatus::Paid, 'paid_at' => now()]);
    }
}
