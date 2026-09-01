<?php

// tests/Feature/Keuangan/GenerateTagihanListenersPpdbConstantTest.php

use App\Domains\Keuangan\Enums\KategoriTagihan;
use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\Tagihan;
use App\Models\Kelas;
use App\Models\Siswa;

it('GenerateTagihanForNewStudent still excludes pendaftaran/daftar_ulang after switching to KategoriTagihan values', function () {
    // Create PPDB jenis_tagihan that should be excluded
    $jenisPendaftaran = JenisTagihan::factory()->create([
        'kategori' => KategoriTagihan::Pendaftaran->value,
        'is_active' => true,
    ]);

    $jenisDaftarUlang = JenisTagihan::factory()->create([
        'kategori' => KategoriTagihan::DaftarUlang->value,
        'is_active' => true,
        'lembaga_id' => $jenisPendaftaran->lembaga_id,
    ]);

    // Create regular jenis_tagihan that should generate tagihan
    $jenisRegular = JenisTagihan::factory()->create([
        'kategori' => KategoriTagihan::Spp->value,
        'is_active' => true,
        'lembaga_id' => $jenisPendaftaran->lembaga_id,
    ]);

    // Create new student
    $siswa = Siswa::factory()->create(['lembaga_id' => $jenisPendaftaran->lembaga_id]);

    // Verify only regular jenis_tagihan generated a tagihan
    expect(Tagihan::where('tagihable_id', $siswa->id)
        ->where('jenis_tagihan_id', $jenisPendaftaran->id)->exists())->toBeFalse();

    expect(Tagihan::where('tagihable_id', $siswa->id)
        ->where('jenis_tagihan_id', $jenisDaftarUlang->id)->exists())->toBeFalse();

    expect(Tagihan::where('tagihable_id', $siswa->id)
        ->where('jenis_tagihan_id', $jenisRegular->id)->exists())->toBeTrue();
});

it('GenerateTagihanForUpdatedClass still excludes pendaftaran/daftar_ulang after switching to KategoriTagihan values', function () {
    // Create PPDB jenis_tagihan that should be excluded
    $jenisPendaftaran = JenisTagihan::factory()->create([
        'kategori' => KategoriTagihan::Pendaftaran->value,
        'is_active' => true,
    ]);

    $jenisDaftarUlang = JenisTagihan::factory()->create([
        'kategori' => KategoriTagihan::DaftarUlang->value,
        'is_active' => true,
        'lembaga_id' => $jenisPendaftaran->lembaga_id,
    ]);

    // Create regular jenis_tagihan that should generate tagihan
    $jenisRegular = JenisTagihan::factory()->create([
        'kategori' => KategoriTagihan::Spp->value,
        'is_active' => true,
        'lembaga_id' => $jenisPendaftaran->lembaga_id,
    ]);

    // Create student without kelas first
    $siswa = Siswa::factory()->create([
        'lembaga_id' => $jenisPendaftaran->lembaga_id,
        'kelas_id' => null,
    ]);

    // Clear tagiha from StudentCreated event
    Tagihan::query()->delete();

    // Create new kelas and update siswa (triggering StudentUpdatedClass)
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $jenisPendaftaran->lembaga_id]);
    $siswa->update(['kelas_id' => $kelasBaru->id]);

    // Verify only regular jenis_tagihan generated a tagihan via StudentUpdatedClass
    expect(Tagihan::where('tagihable_id', $siswa->id)
        ->where('jenis_tagihan_id', $jenisPendaftaran->id)->exists())->toBeFalse();

    expect(Tagihan::where('tagihable_id', $siswa->id)
        ->where('jenis_tagihan_id', $jenisDaftarUlang->id)->exists())->toBeFalse();

    expect(Tagihan::where('tagihable_id', $siswa->id)
        ->where('jenis_tagihan_id', $jenisRegular->id)->exists())->toBeTrue();
});
