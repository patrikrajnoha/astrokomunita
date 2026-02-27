<?php

namespace Database\Seeders;

use App\Services\Bots\BotSourceSyncService;
use Illuminate\Database\Seeder;

class BotSourceSeeder extends Seeder
{
    public function run(): void
    {
        app(BotSourceSyncService::class)->syncDefaults();
    }
}
