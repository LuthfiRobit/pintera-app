<?php

// app/Domains/Keuangan/Models/SiswaKeringanan.php

namespace App\Domains\Keuangan\Models;

use App\Models\Siswa;
use Database\Factories\SiswaKeringananFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiswaKeringanan extends Model
{
    use HasFactory;

    protected static function newFactory(): SiswaKeringananFactory
    {
        return SiswaKeringananFactory::new();
    }

    protected $table = 'siswa_keringanan';

    protected $fillable = ['siswa_id', 'kategori_keringanan_id', 'berlaku_dari', 'berlaku_sampai'];

    protected function casts(): array
    {
        return [
            'berlaku_dari' => 'date',
            'berlaku_sampai' => 'date',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kategoriKeringanan(): BelongsTo
    {
        return $this->belongsTo(KategoriKeringanan::class);
    }
}
