<?php

namespace App\Domains\Keuangan\Models;

use Database\Factories\TagihanItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagihanItem extends Model
{
    use HasFactory;

    protected static function newFactory(): TagihanItemFactory
    {
        return TagihanItemFactory::new();
    }

    protected $table = 'tagihan_item';

    protected $fillable = ['tagihan_id', 'jenis_tagihan_id', 'jumlah'];

    public function tagihan(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tagihan::class);
    }

    public function jenisTagihan(): BelongsTo
    {
        return $this->belongsTo(JenisTagihan::class);
    }
}
