<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class TahunAjaran extends Model
{
    use BelongsToTenant;

    protected $table = 'tahun_ajaran';

    protected $fillable = ['lembaga_id', 'nama', 'tanggal_mulai', 'tanggal_selesai', 'status_aktif'];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'status_aktif' => 'boolean',
        ];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function semester(): HasMany
    {
        return $this->hasMany(Semester::class);
    }

    public function activate(): void
    {
        DB::transaction(function () {
            static::withoutGlobalScopes()
                ->where('lembaga_id', $this->lembaga_id)
                ->where('id', '!=', $this->id)
                ->update(['status_aktif' => false]);

            $this->forceFill(['status_aktif' => true])->save();
        });
    }
}
