<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    public $timestamps = false;
    protected $guarded = [];
    protected function casts(): array { return ['is_winner' => 'boolean', 'created_at' => 'datetime']; }
}
