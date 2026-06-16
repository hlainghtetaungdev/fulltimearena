<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveMatch extends Model
{
    protected $guarded = [];
    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_live' => 'boolean', 'streams_json' => 'array'];
    }
}
