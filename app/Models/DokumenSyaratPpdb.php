<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DokumenSyaratPpdb extends Model
{
    use BelongsToTenant;

    protected $table = 'dokumen_syarat_ppdb';

    protected $fillable = ['jalur_ppdb_id', 'lembaga_id', 'nama_dokumen', 'wajib', 'urutan'];

    protected function casts(): array
    {
        return [
            'wajib' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DokumenSyaratPpdb $dokumen) {
            if (empty($dokumen->lembaga_id)) {
                $dokumen->lembaga_id = JalurPpdb::withoutGlobalScopes()
                    ->findOrFail($dokumen->jalur_ppdb_id)
                    ->lembaga_id;
            }
        });
    }

    public function jalurPpdb(): BelongsTo
    {
        return $this->belongsTo(JalurPpdb::class, 'jalur_ppdb_id');
    }
}
