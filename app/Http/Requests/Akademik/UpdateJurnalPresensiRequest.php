<?php

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\DataTransferObjects\JurnalPresensiData;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateJurnalPresensiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
