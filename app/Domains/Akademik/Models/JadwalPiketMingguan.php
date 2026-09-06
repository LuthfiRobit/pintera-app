<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPiketMingguan extends Model
{
    use BelongsToTenant;

    protected $table = 'jadwal_piket_mingguan';

    protected $fillable = ['lembaga_id', 'guru_id', 'hari', 'semester_id', 'dibuat_oleh_user_id'];

    public function guru(): BelongsTo
    {
        return $this->belongsTo(Guru::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh_user_id');
    }
}
