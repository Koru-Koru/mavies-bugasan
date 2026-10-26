<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    protected $primaryKey = 'setting_key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    protected $casts = ['updated_at' => 'datetime'];

    public static function value(string $key, ?string $default = null): ?string
    {
        return static::query()->whereKey($key)->value('setting_value') ?? $default;
    }
}
