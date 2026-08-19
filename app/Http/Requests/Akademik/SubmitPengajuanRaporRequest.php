<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitPengajuanRaporRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('rapor.ajukan');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'semester_id' => ['required', 'integer', 'exists:semester,id'],
        ];
    }
}
