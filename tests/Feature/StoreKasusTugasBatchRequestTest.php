<?php
// tests/Feature/StoreKasusTugasBatchRequestTest.php

use App\Http\Requests\StoreKasusTugasBatchRequest;
use Illuminate\Support\Facades\Validator;

function validasiTugasBatch(array $data): \Illuminate\Contracts\Validation\Validator
{
    $request = new StoreKasusTugasBatchRequest();
    $request->merge($data);

    return Validator::make($data, $request->rules(), [], []);
}

it('passes with a minimal valid sekali payload', function () {
    $validator = validasiTugasBatch([
        'judul' => 'Refleksi Mingguan',
        'instruksi' => 'Tulis refleksi.',
        'frekuensi' => 'sekali',
        'tanggal_mulai' => '2026-08-10',
        'tanggal_selesai' => '2026-08-10',
    ]);

    expect($validator->fails())->toBeFalse();
});

it('rejects tanggal_selesai before tanggal_mulai', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'sekali',
        'tanggal_mulai' => '2026-08-10', 'tanggal_selesai' => '2026-08-05',
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tanggal_selesai'))->toBeTrue();
});

it('requires tanggal_pengumpulan_bulanan when the final frequency is bulanan', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31',
    ]);

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('tanggal_pengumpulan_bulanan'))->toBeTrue();
});

it('does not require tanggal_pengumpulan_bulanan when bulanan falls back to mingguan', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-08-20',
    ]);

    expect($validator->fails())->toBeFalse();
});

it('accepts tanggal_pengumpulan_bulanan as an integer between 1 and 31', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31',
        'tanggal_pengumpulan_bulanan' => '15',
    ]);

    expect($validator->fails())->toBeFalse();
});

it('accepts akhir_bulan as the tanggal_pengumpulan_bulanan value', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31',
        'tanggal_pengumpulan_bulanan' => 'akhir_bulan',
    ]);

    expect($validator->fails())->toBeFalse();
});

it('rejects a tanggal_pengumpulan_bulanan value that is neither a valid day nor akhir_bulan', function () {
    $validator = validasiTugasBatch([
        'judul' => 'X', 'instruksi' => 'Y', 'frekuensi' => 'bulanan',
        'tanggal_mulai' => '2026-08-01', 'tanggal_selesai' => '2026-12-31',
        'tanggal_pengumpulan_bulanan' => '35',
    ]);

    expect($validator->fails())->toBeTrue();
});
