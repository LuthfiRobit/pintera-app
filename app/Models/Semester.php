<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class Semester extends Model
{
    use BelongsToTenant;

    protected $table = 'semester';

    protected $fillable = [
        'tahun_ajaran_id', 'lembaga_id', 'nama', 'urutan', 'kode_dapodik',
        'tanggal_mulai', 'tanggal_selesai', 'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'status_aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Semester $semester) {
            if (empty($semester->lembaga_id)) {
                $semester->lembaga_id = TahunAjaran::withoutGlobalScopes()
                    ->findOrFail($semester->tahun_ajaran_id)
                    ->lembaga_id;
            }
        });
    }

    public function tahunAjaran(): BelongsTo
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function activate(): void
    {
        if (! $this->tahunAjaran()->withoutGlobalScopes()->first()->status_aktif) {
            throw new RuntimeException('Tahun ajaran induk harus aktif sebelum semester ini bisa diaktifkan.');
        }

        DB::transaction(function () {
            static::withoutGlobalScopes()
                ->where('lembaga_id', $this->lembaga_id)
                ->where('id', '!=', $this->id)
                ->update(['status_aktif' => false]);

            $this->forceFill(['status_aktif' => true])->save();
        });
    }

    public function dataPeriodikLembaga(): HasMany
    {
        return $this->hasMany(LembagaDataPeriodik::class);
    }
}
