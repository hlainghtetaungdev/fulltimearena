<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $primaryKey = 'setting_key';
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $fillable = ['setting_key', 'setting_value'];

    public static function values(): array
    {
        return static::query()->pluck('setting_value', 'setting_key')->all();
    }
}
