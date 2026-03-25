<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(BotSourceSeeder::class);
        $this->call(EventSourceSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DefaultUsersSeeder::class);
            $this->call(DemoFeedSeeder::class);
        }

        $this->call(TranslationOverrideSeeder::class);
    }
}
