<?php

namespace App\Domains\Sdm\Models;

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PengajuanIzinCuti extends Model
{
    use BelongsToTenant;

    protected $table = 'pengajuan_izin_cuti';

    protected $fillable = ['lembaga_id', 'pegawai_type', 'pegawai_id', 'kategori', 'tanggal_mulai', 'tanggal_selesai', 'alasan'];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriPengajuanIzin::class,
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function pegawai(): MorphTo
    {
        return $this->morphTo();
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }

    public function approvalRequest(): MorphOne
    {
        return $this->morphOne(ApprovalRequest::class, 'approvable');
    }
}
