<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    public $timestamps = false;
    protected $fillable = ['name', 'link_url', 'icon_path', 'sort_order', 'active'];
    protected function casts(): array { return ['active' => 'boolean']; }
}
