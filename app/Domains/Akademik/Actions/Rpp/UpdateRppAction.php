<?php

declare(strict_types=1);

namespace App\Domains\Akademik\Actions\Rpp;

use App\Domains\Akademik\DataTransferObjects\RppData;
use App\Domains\Akademik\Models\Rpp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class UpdateRppAction
{
    /**
     * @throws ValidationException
     */
    public function execute(Rpp $rpp, RppData $data): Rpp
    {
        if (! $rpp->canBeEditedByGuru()) {
            throw ValidationException::withMessages([
                'status' => 'Dokumen RPP ini sedang diverifikasi atau sudah disetujui, sehingga tidak dapat disunting.',
            ]);
        }

        $storedPath = $rpp->file_path;
        $originalFileName = $rpp->file_name;
        $fileSize = $rpp->file_size_bytes;
        $mimeType = $rpp->mime_type;

        if ($data->file) {
            if ($rpp->file_path && Storage::disk('public')->exists($rpp->file_path)) {
                Storage::disk('public')->delete($rpp->file_path);
            }

            $file = $data->file;
            $originalFileName = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getClientMimeType() ?: $file->getMimeType() ?: 'application/octet-stream';
            $storedPath = $file->store("rpp/{$data->lembagaId}", 'public');
        }

        return DB::transaction(function () use ($rpp, $data, $storedPath, $originalFileName, $fileSize, $mimeType) {
            $rpp->update([
                'kelas_id' => $data->kelasId,
                'mata_pelajaran_id' => $data->mataPelajaranId,
                'judul_topik' => $data->judulTopik,
                'alokasi_waktu' => $data->alokasiWaktu,
                'pertemuan_ke' => $data->pertemuanKe,
                'file_path' => $storedPath,
                'file_name' => $originalFileName,
                'file_size_bytes' => $fileSize,
                'mime_type' => $mimeType,
            ]);

            return $rpp->fresh();
        });
    }
}
