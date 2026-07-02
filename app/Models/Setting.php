<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getByKey(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function setByKey(string $key, $value): self
    {
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => is_null($value) ? null : (string) $value]
        );
    }
}
