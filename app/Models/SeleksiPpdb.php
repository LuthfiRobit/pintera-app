<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeleksiPpdb extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'seleksi_ppdb';

    protected $fillable = ['jalur_ppdb_id', 'gelombang_ppdb_id', 'lembaga_id', 'jenis_tes_master_id', 'jadwal', 'kriteria_kelulusan', 'bobot'];

    protected function casts(): array
    {
        return [
            'jadwal' => 'datetime',
            'bobot' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SeleksiPpdb $seleksi) {
            if (empty($seleksi->lembaga_id)) {
                $seleksi->lembaga_id = JalurPpdb::withoutGlobalScopes()
                    ->findOrFail($seleksi->jalur_ppdb_id)
                    ->lembaga_id;
            }
        });
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class, 'jalur_ppdb_id');
    }

    public function gelombangPpdb(): BelongsTo
    {
        return $this->belongsTo(GelombangPpdb::class, 'gelombang_ppdb_id');
    }

    public function jenisTesMaster(): BelongsTo
    {
        return $this->belongsTo(JenisTesMaster::class, 'jenis_tes_master_id');
    }
}
