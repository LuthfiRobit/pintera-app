<?php

namespace App\Domains\Sarpras\Models;

use App\Domains\Sarpras\Enums\KondisiAset;
use App\Domains\Sarpras\Enums\SumberPerolehanAset;
use App\Domains\Sarpras\Enums\TipePencatatanAset;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Lembaga;
use App\Models\Yayasan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AsetBarang extends Model
{
    use BelongsToTenant, HasFactory, LogsActivity;

    protected $table = 'aset_barang';

    protected $fillable = [
        'yayasan_id',
        'lembaga_id',
        'kategori_aset_id',
        'ruangan_id',
        'kode_inventaris',
        'nama_barang',
        'merk',
        'spesifikasi',
        'tipe_pencatatan',
        'qty',
        'satuan',
        'kondisi',
        'sumber_perolehan',
        'tanggal_perolehan',
        'harga_perolehan',
        'foto_barang_path',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'harga_perolehan' => 'decimal:2',
            'tanggal_perolehan' => 'date',
            'tipe_pencatatan' => TipePencatatanAset::class,
            'kondisi' => KondisiAset::class,
            'sumber_perolehan' => SumberPerolehanAset::class,
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

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'kategori_aset_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }

    public function riwayatMutasi(): HasMany
    {
        return $this->hasMany(RiwayatMutasiAset::class, 'aset_barang_id')->latest('tanggal_mutasi');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['nama_barang', 'kode_inventaris', 'qty', 'kondisi', 'ruangan_id'])
            ->logOnlyDirty()
            ->useLogName('sarpras_aset');
    }
}
