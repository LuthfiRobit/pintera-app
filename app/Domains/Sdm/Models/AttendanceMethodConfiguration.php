<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\AttendanceMethod;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMethodConfiguration extends Model
{
    use BelongsToTenant;

    protected $table = 'attendance_method_configurations';

    protected $attributes = [
        'is_enabled' => true,
    ];

    protected $fillable = ['yayasan_id', 'lembaga_id', 'method', 'is_enabled'];

    protected function casts(): array
    {
        return [
            'method' => AttendanceMethod::class,
            'is_enabled' => 'boolean',
        ];
    }

    public function yayasan(): BelongsTo
    {
        return $this->belongsTo(Yayasan::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
