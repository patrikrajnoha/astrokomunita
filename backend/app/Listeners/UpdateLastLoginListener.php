<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;

class UpdateLastLoginListener
{
    public function handle(Login $event): void
    {
        return;
    }
}
