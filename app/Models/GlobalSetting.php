<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GlobalSetting extends Model
{
    protected $table = 'global_settings';

    protected $fillable = ['key', 'value'];

    public static function getByKey(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting) {
            // Provide sensible defaults if not seeded
            $defaults = [
                'platform_commission_rate' => 1.0,
                'default_free_tier_limit' => 50,
                'maintenance_mode' => false,
                'stripe_mock_mode' => false,
            ];
            return $defaults[$key] ?? $default;
        }

        $val = $setting->value;

        // Explicit type casting for known keys
        if ($key === 'platform_commission_rate') {
            return (float) $val;
        }
        if ($key === 'default_free_tier_limit') {
            return (int) $val;
        }
        if ($key === 'maintenance_mode' || $key === 'stripe_mock_mode') {
            return $val === 'true' || $val === '1' || $val === 1 || $val === true;
        }

        if ($val === 'true') return true;
        if ($val === 'false') return false;
        if (is_numeric($val)) {
            return strpos($val, '.') !== false ? (float)$val : (int)$val;
        }
        return $val;
    }

    public static function setByKey(string $key, $value): self
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        return self::updateOrCreate(
            ['key' => $key],
            ['value' => is_null($value) ? null : (string) $value]
        );
    }
}
