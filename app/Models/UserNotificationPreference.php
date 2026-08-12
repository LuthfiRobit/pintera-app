<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotificationPreference extends Model
{
    protected $fillable = ['user_id', 'module', 'channel_push', 'channel_wa', 'channel_email'];

    protected $attributes = [
        'module' => 'finance',
        'channel_push' => false,
        'channel_wa' => true,
        'channel_email' => true,
    ];

    protected function casts(): array
    {
        return [
            'channel_push' => 'boolean',
            'channel_wa' => 'boolean',
            'channel_email' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
