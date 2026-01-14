<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }
}
