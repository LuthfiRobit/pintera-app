<?php

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use App\Domains\Akademik\Models\SesiPembelajaran;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateJurnalPresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $sesi = $this->route('sesi');
        $guru = $this->user()?->guru;

        return $guru !== null && $sesi instanceof SesiPembelajaran && $sesi->guru_id === $guru->id;
    }

    public function rules(): array
    {
        return [
            'materi' => ['nullable', 'string'],
            'presensi' => ['required', 'array'],
            'presensi.*' => ['required', 'in:hadir,izin,sakit,alpa,terlambat'],
        ];
    }

    public function toDTO(): JurnalPresensiData
    {
        return JurnalPresensiData::fromArray($this->validated());
    }
}
