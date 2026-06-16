<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ad extends Model
{
    public $timestamps = false;
    protected $fillable = ['image_path', 'link_url', 'sort_order', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
