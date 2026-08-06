<?php
// app/Http/Requests/PreviewKasusTugasBatchRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewKasusTugasBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Rule set yang lebih longgar dari StoreKasusTugasBatchRequest: pratinjau hanya perlu
     * frekuensi + rentang tanggal (+ tanggal_pengumpulan_bulanan mentah jika sudah diisi)
     * untuk menghitung frekuensi final dan baris tanggal. `judul`/`instruksi` sengaja tidak
     * divalidasi di sini — pratinjau tidak menulis apa pun, dan mewajibkannya membuat
     * permintaan pratinjau 422 sebelum kolom itu sempat diisi konselor. `tanggal_pengumpulan_bulanan`
     * juga sengaja tidak required_if di sini — tugas pratinjau justru memberi tahu frontend
     * "kolom ini akan dibutuhkan", bukan menolak permintaan karena kolom itu belum ada.
     */
    public function rules(): array
    {
        return [
            'frekuensi' => ['required', 'in:sekali,harian,mingguan,bulanan'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'tanggal_pengumpulan_bulanan' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '' || $value === 'akhir_bulan') {
                        return;
                    }
                    if (! is_numeric($value) || (int) $value < 1 || (int) $value > 31) {
                        $fail('Tanggal pengumpulan bulanan harus berupa angka 1-31 atau "akhir_bulan".');
                    }
                },
            ],
        ];
    }
}
