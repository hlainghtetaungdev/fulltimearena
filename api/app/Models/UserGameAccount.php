<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserGameAccount extends Model
{
    protected $guarded = [];
    protected $hidden = ['external_password_enc', 'api_payload', 'api_response'];
}
