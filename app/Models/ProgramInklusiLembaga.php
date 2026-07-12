<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramInklusiLembaga extends Model
{
    use BelongsToTenant;

    protected $table = 'program_inklusi_lembaga';

    protected $fillable = ['lembaga_id', 'kebutuhan_khusus', 'no_sk', 'tanggal_sk', 'tmt', 'tst', 'keterangan'];

    protected function casts(): array
    {
        return ['tanggal_sk' => 'date', 'tmt' => 'date', 'tst' => 'date'];
    }

    public function lembaga(): BelongsTo
    {
        return $this->belongsTo(Lembaga::class);
    }
}
