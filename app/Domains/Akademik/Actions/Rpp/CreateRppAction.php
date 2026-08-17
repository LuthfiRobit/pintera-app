<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rpp;

use App\Domains\Akademik\DataTransferObjects\RppData;
use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class CreateRppAction
{
    /**
     * @throws ValidationException
     */
    public function execute(RppData $data): Rpp
    {
        if (! $data->file) {
            throw ValidationException::withMessages([
                'file' => 'Berkas RPP / Modul Ajar wajib diunggah.',
            ]);
        }

        $file = $data->file;
        $originalFileName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        $mimeType = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';

        $storedPath = $file->store("rpp/{$data->lembagaId}", 'public');

        return DB::transaction(function () use ($data, $storedPath, $originalFileName, $fileSize, $mimeType) {
            return Rpp::create([
                'yayasan_id' => $data->yayasanId,
                'lembaga_id' => $data->lembagaId,
                'guru_id' => $data->guruId,
                'tahun_ajaran_id' => $data->tahunAjaranId,
                'semester_id' => $data->semesterId,
                'kelas_id' => $data->kelasId,
                'mata_pelajaran_id' => $data->mataPelajaranId,
                'judul_topik' => $data->judulTopik,
                'alokasi_waktu' => $data->alokasiWaktu,
                'pertemuan_ke' => $data->pertemuanKe,
                'file_path' => $storedPath,
                'file_name' => $originalFileName,
                'file_size_bytes' => $fileSize,
                'mime_type' => $mimeType,
                'status' => StatusRpp::Draft,
            ]);
        });
    }
}
