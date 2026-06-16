<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;
    protected $fillable = ['agent_id', 'user_id', 'title', 'body', 'active'];
    protected function casts(): array { return ['active' => 'boolean', 'created_at' => 'datetime']; }
}
