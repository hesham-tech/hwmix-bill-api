<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupSetting extends Model
{
    protected $table = 'backup_settings';
    protected $guarded = [];

    /**
     * Get a setting value by key.
     */
    public static function getVal($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        if (!$setting)
            return $default;

        switch ($setting->type) {
            case 'integer':
                return (int) $setting->value;
            case 'boolean':
                return (bool) $setting->value;
            case 'json':
                return json_decode($setting->value, true);
            default:
                return $setting->value;
        }
    }
}
