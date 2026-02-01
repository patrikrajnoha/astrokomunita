<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class AstroBotEnsureUser extends Command
{
    protected $signature = 'astrobot:ensure-user';
    protected $description = 'Ensure AstroBot user exists (create if missing)';

    public function handle(): int
    {
        $user = User::firstOrCreate(
            ['email' => 'astrobot@astrokomunita.local'],
            [
                'name' => 'AstroBot',
                'bio' => 'Automated space news from NASA RSS',
                'password' => Str::random(40),
            ]
        );

        $this->info('AstroBot user ensured. ID: ' . $user->id . ', Email: ' . $user->email);

        return Command::SUCCESS;
    }
}
