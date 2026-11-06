<?php

namespace Database\Factories;

use App\Enums\PhotoStatus;
use App\Models\Event;
use App\Models\Photo;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Photo> */
class PhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'original_name' => fake()->uuid().'.jpg',
            'original_path' => 'events/test/originals/'.fake()->uuid().'.jpg',
            'size_bytes' => 1000,
            'checksum' => hash('sha256', fake()->uuid()),
            'status' => PhotoStatus::Received,
        ];
    }
}
