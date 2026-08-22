<?php
// tests/Feature/Sdm/PengajuanIzinCutiModelTest.php

use App\Domains\Sdm\Enums\KategoriPengajuanIzin;
use App\Domains\Sdm\Models\PengajuanIzinCuti;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\Yayasan;

it('creates a pengajuan izin cuti for a guru via the morph relation', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $pengajuan = $guru->pengajuanIzinCuti()->create([
        'lembaga_id' => $lembaga->id, 'kategori' => KategoriPengajuanIzin::Sakit,
        'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-09-02', 'alasan' => 'Demam tinggi.',
    ]);

    expect($pengajuan->pegawai_type)->toBe(Guru::class);
    expect($pengajuan->kategori)->toBe(KategoriPengajuanIzin::Sakit);
    expect($guru->pengajuanIzinCuti()->count())->toBe(1);
});

it('maps each kategori to the correct AttendanceStatus', function () {
    expect(KategoriPengajuanIzin::Izin->toAttendanceStatus())->toBe(\App\Domains\Sdm\Enums\AttendanceStatus::Izin);
    expect(KategoriPengajuanIzin::Sakit->toAttendanceStatus())->toBe(\App\Domains\Sdm\Enums\AttendanceStatus::Sakit);
    expect(KategoriPengajuanIzin::Cuti->toAttendanceStatus())->toBe(\App\Domains\Sdm\Enums\AttendanceStatus::Cuti);
});
