<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentProviderConfig extends Model
{
    protected $guarded = [];
    protected $hidden = ['agent_username_enc', 'agent_password_enc'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
