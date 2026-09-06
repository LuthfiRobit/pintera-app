<?php

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Domains\Akademik\Services\PiketAccessChecker;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateJurnalPresensiRequest extends FormRequest
{
    // Sole ownership enforcement point for the update route (mirrors, but is not
    // called by, JurnalKbmController::authorizeMilikGuru(), which still guards show()).
    // Kedua titik ini WAJIB memakai PiketAccessChecker yang sama -- jangan tulis ulang logic beda.
    public function authorize(): bool
    {
        $sesi = $this->route('sesi');
        $guru = $this->user()?->guru;

        if ($guru === null || ! $sesi instanceof SesiPembelajaran) {
            return false;
        }

        return app(PiketAccessChecker::class)->bisaAkses($sesi, $guru);
    }

    public function rules(): array
    {
        return [
            'materi' => ['nullable', 'string'],
            'presensi' => ['required', 'array'],
            'presensi.*' => ['required', 'in:hadir,izin,sakit,alpa,terlambat'],
            'keterangan' => ['nullable', 'array'],
            'keterangan.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function toDTO(): JurnalPresensiData
    {
        return JurnalPresensiData::fromArray($this->validated());
    }
}
