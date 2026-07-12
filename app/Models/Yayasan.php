<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Yayasan extends Model
{
    use HasFactory, LogsActivity;

    protected $table = 'yayasan';

    protected $fillable = [
        'nama', 'npwp_yayasan', 'akta_pendirian_nomor', 'akta_pendirian_tanggal',
        'sk_kemenkumham_nomor', 'alamat', 'telepon', 'email', 'website',
        'logo', 'nama_ketua_pembina', 'nama_ketua_pengurus',
    ];

    protected function casts(): array
    {
        return [
            'akta_pendirian_tanggal' => 'date',
            'npwp_yayasan' => 'encrypted',
        ];
    }

    public function lembaga(): HasMany
    {
        return $this->hasMany(Lembaga::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'nama', 'akta_pendirian_nomor', 'akta_pendirian_tanggal', 'sk_kemenkumham_nomor',
                'alamat', 'telepon', 'email', 'website', 'nama_ketua_pembina', 'nama_ketua_pengurus',
            ])
            ->logOnlyDirty()
            ->useLogName('yayasan');
    }
}
