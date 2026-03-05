<?php

namespace Database\Factories;

use App\Models\Observation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Observation>
 */
class ObservationFactory extends Factory
{
    protected $model = Observation::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'event_id' => null,
            'feed_post_id' => null,
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'observed_at' => now()->subHours(random_int(1, 48)),
            'location_lat' => 48.1486,
            'location_lng' => 17.1077,
            'location_name' => 'Bratislava',
            'visibility_rating' => 4,
            'equipment' => 'Dobsonian 200/1200',
            'is_public' => true,
        ];
    }
}
