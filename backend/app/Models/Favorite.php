<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorite extends Model
{
    protected $fillable = ['user_id', 'event_id'];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}
