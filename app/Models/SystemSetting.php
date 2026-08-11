<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'lembaga_id',
        'key',
        'value',
        'description',
        'updated_by',
    ];

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Resolve setting value with fallback logic (lembaga -> global).
     */
    public static function getResolved(string $key, ?int $lembagaId = null, $default = null)
    {
        $cacheKey = "setting_{$key}_lembaga_" . ($lembagaId ?? 'global');
        
        return Cache::rememberForever($cacheKey, function () use ($key, $lembagaId, $default) {
            if ($lembagaId) {
                $setting = self::where('lembaga_id', $lembagaId)->where('key', $key)->first();
                if ($setting) {
                    return $setting->value;
                }
            }

            $globalSetting = self::whereNull('lembaga_id')->where('key', $key)->first();
            return $globalSetting ? $globalSetting->value : $default;
        });
    }
}
