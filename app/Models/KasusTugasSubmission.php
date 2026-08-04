<?php
// app/Models/KasusTugasSubmission.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KasusTugasSubmission extends Model
{
    use HasFactory;

    protected $table = 'kasus_tugas_submission';

    protected $fillable = [
        'tugas_id', 'siswa_id', 'orang_tua_id', 'teks', 'lampiran',
        'status_review', 'catatan_revisi',
    ];

    protected $attributes = [
        'status_review' => 'menunggu_review',
    ];

    protected static function booted(): void
    {
        static::created(function (KasusTugasSubmission $submission) {
            $tugas = $submission->tugas;

            if ($tugas->status === \App\Enums\StatusKasusTugas::Ditugaskan) {
                $tugas->update(['status' => \App\Enums\StatusKasusTugas::Dikerjakan]);
            }
        });
    }

    public function tugas(): BelongsTo
    {
        return $this->belongsTo(KasusTugas::class, 'tugas_id');
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function orangTua(): BelongsTo
    {
        return $this->belongsTo(OrangTua::class, 'orang_tua_id');
    }
}
