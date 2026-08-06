<?php
// app/Http/Requests/StoreKasusTugasBatchRequest.php

namespace App\Http\Requests;

use App\Services\TugasBatchGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKasusTugasBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $frekuensiAkhir = $this->frekuensiAkhir();

        return [
            'judul' => ['required', 'string', 'max:255'],
            'instruksi' => ['required', 'string'],
            'frekuensi' => ['required', 'in:sekali,harian,mingguan,bulanan'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'tanggal_pengumpulan_bulanan' => [
                Rule::requiredIf($frekuensiAkhir === 'bulanan'),
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === 'akhir_bulan') {
                        return;
                    }
                    if (! is_numeric($value) || (int) $value < 1 || (int) $value > 31) {
                        $fail('Tanggal pengumpulan bulanan harus berupa angka 1-31 atau "akhir_bulan".');
                    }
                },
            ],
        ];
    }

    private function frekuensiAkhir(): ?string
    {
        $frekuensi = $this->input('frekuensi');
        $mulai = $this->input('tanggal_mulai');
        $selesai = $this->input('tanggal_selesai');

        if (! $frekuensi || ! $mulai || ! $selesai || ! strtotime($mulai) || ! strtotime($selesai)) {
            return null;
        }

        return (new TugasBatchGenerator())->tentukanFrekuensiAkhir($frekuensi, Carbon::parse($mulai), Carbon::parse($selesai));
    }
}
