<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AstroBotSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'astrobot@astrokomunita.local'],
            [
                'name' => 'AstroBot',
                'bio' => 'Automated space news from NASA RSS',
                'password' => Str::random(40),
                'is_bot' => true, // Mark as bot user
            ]
        );
    }
}
