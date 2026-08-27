<?php

declare(strict_types=1);

namespace App\Http\Requests\Akademik;

use App\Domains\Akademik\Enums\BentukPendidikan;
use App\Domains\Akademik\Enums\KurikulumFramework;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateKurikulumAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bentuk_pendidikan' => ['required', Rule::enum(BentukPendidikan::class)],
            'tingkat' => ['nullable', 'string', 'max:10'],
            'kurikulum' => ['required', Rule::enum(KurikulumFramework::class)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $bentukPendidikan = $this->input('bentuk_pendidikan');
            $tingkat = $this->input('tingkat');

            if ($tingkat === null || $tingkat === '' || ! BentukPendidikan::tryFrom((string) $bentukPendidikan)) {
                return;
            }

            $valid = BentukPendidikan::from($bentukPendidikan)->validTingkatValues();

            if (! in_array($tingkat, $valid, true)) {
                $validator->errors()->add('tingkat', "Tingkat '{$tingkat}' tidak valid untuk bentuk pendidikan {$bentukPendidikan}. Nilai valid: ".implode(', ', $valid).'.');
            }
        });
    }
}
