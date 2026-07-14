<?php

use App\Models\CalonMurid;
use App\Models\DokumenPendaftaran;
use App\Models\DokumenSyaratPpdb;
use App\Models\GelombangPpdb;
use App\Models\HasilSeleksi;
use App\Models\JalurPpdb;
use App\Models\JenisTesMaster;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SeleksiPpdb;
use App\Models\SkPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatKonteksM3Verifikasi(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::create([
        'lembaga_id' => $lembaga->id, 'nama' => '2026/2027',
        'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'status_aktif' => true,
    ]);
    $jalur = JalurPpdb::create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Reguler']);
    $gelombang = GelombangPpdb::create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Gelombang 1',
        'tanggal_buka' => now()->subDay(), 'tanggal_tutup' => now()->addMonth(), 'kuota' => 40,
    ]);
    $calonMurid = CalonMurid::factory()->create(['yayasan_id' => $yayasan->id]);
    $pendaftaran = Pendaftaran::create([
        'calon_murid_id' => $calonMurid->id, 'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id,
        'kode_pendaftaran' => 'REG-2026-00001', 'email_pendaftaran' => 'wali@example.test', 'submitted_at' => now(),
    ]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    return [$lembaga, $gelombang, $jalur, $pendaftaran, $user];
}

it('records verification audit fields on a dokumen pendaftaran', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'pendaftaran/1/akta.pdf', 'nama_file_asli' => 'akta.pdf',
        'mime_type' => 'application/pdf', 'ukuran_bytes' => 1000,
    ]);

    $dokumen->update([
        'status_verifikasi' => 'ditolak',
        'catatan_verifikasi' => 'Foto buram, tidak terbaca.',
        'diverifikasi_oleh_user_id' => $user->id,
        'diverifikasi_pada' => now(),
    ]);

    $dokumen->refresh();
    expect($dokumen->status_verifikasi)->toBe('ditolak');
    expect($dokumen->catatan_verifikasi)->toBe('Foto buram, tidak terbaca.');
    expect($dokumen->diverifikasiOleh->id)->toBe($user->id);
    expect($dokumen->diverifikasi_pada)->not->toBeNull();
});

it('records decision audit fields and an optional sk_ppdb link on a pendaftaran', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();

    $pendaftaran->update([
        'status' => 'diterima',
        'catatan_keputusan' => 'Nilai memenuhi syarat.',
        'ditetapkan_oleh_user_id' => $user->id,
        'ditetapkan_pada' => now(),
    ]);

    $pendaftaran->refresh();
    expect($pendaftaran->status)->toBe('diterima');
    expect($pendaftaran->ditetapkanOleh->id)->toBe($user->id);
    expect($pendaftaran->sk_ppdb_id)->toBeNull();

    $sk = SkPpdb::create([
        'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $user->id, 'file_path' => 'sk/1/sk.pdf',
    ]);
    $pendaftaran->update(['sk_ppdb_id' => $sk->id]);

    expect($pendaftaran->fresh()->skPpdb->nomor_sk)->toBe('421.3/SK-PPDB.001/2026');
    expect($sk->pendaftaran)->toHaveCount(1);
    expect($sk->gelombangPpdb->id)->toBe($gelombang->id);
    expect($sk->diterbitkanOleh->id)->toBe($user->id);
});

it('records manual nilai per pendaftaran x seleksi_ppdb pair, one row per pair', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);

    $hasil = HasilSeleksi::updateOrCreate(
        ['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id],
        ['nilai' => 85.5, 'catatan' => 'Baik', 'dinilai_oleh_user_id' => $user->id, 'dinilai_pada' => now()]
    );

    expect(HasilSeleksi::count())->toBe(1);
    expect($hasil->pendaftaran->id)->toBe($pendaftaran->id);
    expect($hasil->seleksiPpdb->id)->toBe($seleksi->id);
    expect((float) $hasil->nilai)->toBe(85.5);

    // Re-entry via the same pair updates the existing row, never creates a second one.
    HasilSeleksi::updateOrCreate(
        ['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id],
        ['nilai' => 90, 'dinilai_oleh_user_id' => $user->id, 'dinilai_pada' => now()]
    );

    expect(HasilSeleksi::count())->toBe(1);
    expect((float) HasilSeleksi::first()->nilai)->toBe(90.0);
});

it('rejects a duplicate nilai row for the same pendaftaran x seleksi_ppdb pair inserted directly', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();
    $jenisTes = JenisTesMaster::create(['lembaga_id' => $lembaga->id, 'nama' => 'Tes Tulis']);
    $seleksi = SeleksiPpdb::create([
        'jalur_ppdb_id' => $jalur->id, 'gelombang_ppdb_id' => $gelombang->id, 'jenis_tes_master_id' => $jenisTes->id,
        'jadwal' => now()->addWeek(), 'kriteria_kelulusan' => 'Nilai minimal 65', 'bobot' => 60,
    ]);
    HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 80]);

    expect(fn () => HasilSeleksi::create(['pendaftaran_id' => $pendaftaran->id, 'seleksi_ppdb_id' => $seleksi->id, 'nilai' => 70]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

it('rejects a duplicate nomor_sk for the same lembaga but allows it for a different lembaga', function () {
    [$lembagaA, $gelombangA] = buatKonteksM3Verifikasi();
    [$lembagaB, $gelombangB] = buatKonteksM3Verifikasi();
    $userA = User::factory()->create(['lembaga_id' => $lembagaA->id]);

    SkPpdb::create([
        'gelombang_ppdb_id' => $gelombangA->id, 'lembaga_id' => $lembagaA->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $userA->id, 'file_path' => 'sk/a/sk.pdf',
    ]);

    expect(fn () => SkPpdb::create([
        'gelombang_ppdb_id' => $gelombangA->id, 'lembaga_id' => $lembagaA->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $userA->id, 'file_path' => 'sk/a2/sk.pdf',
    ]))->toThrow(\Illuminate\Database\QueryException::class);

    $userB = User::factory()->create(['lembaga_id' => $lembagaB->id]);
    $skLembagaB = SkPpdb::create([
        'gelombang_ppdb_id' => $gelombangB->id, 'lembaga_id' => $lembagaB->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $userB->id, 'file_path' => 'sk/b/sk.pdf',
    ]);

    expect($skLembagaB->id)->not->toBeNull();
});

it('allows a gelombang to have more than one sk_ppdb over time', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();

    $skPertama = SkPpdb::create([
        'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
        'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
        'diterbitkan_oleh_user_id' => $user->id, 'file_path' => 'sk/1/sk.pdf',
    ]);
    $skSusulan = SkPpdb::create([
        'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
        'nomor_sk' => '421.3/SK-PPDB.002-SUSULAN/2026', 'tanggal_terbit' => now()->addWeek()->toDateString(),
        'diterbitkan_oleh_user_id' => $user->id, 'file_path' => 'sk/2/sk.pdf',
    ]);

    expect(SkPpdb::where('gelombang_ppdb_id', $gelombang->id)->count())->toBe(2);
    expect($skPertama->id)->not->toBe($skSusulan->id);
});

it('logs activity when a pendaftaran decision changes', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();

    $pendaftaran->update(['status' => 'diterima', 'ditetapkan_oleh_user_id' => $user->id]);

    expect(\Spatie\Activitylog\Models\Activity::where('log_name', 'pendaftaran')->where('subject_id', $pendaftaran->id)->exists())->toBeTrue();
});

it('logs activity when a dokumen pendaftaran verification status changes', function () {
    [$lembaga, $gelombang, $jalur, $pendaftaran, $user] = buatKonteksM3Verifikasi();
    $syarat = DokumenSyaratPpdb::create(['jalur_ppdb_id' => $jalur->id, 'nama_dokumen' => 'Akta Kelahiran']);
    $dokumen = DokumenPendaftaran::create([
        'pendaftaran_id' => $pendaftaran->id, 'dokumen_syarat_ppdb_id' => $syarat->id,
        'file_path' => 'pendaftaran/1/akta.pdf', 'nama_file_asli' => 'akta.pdf',
        'mime_type' => 'application/pdf', 'ukuran_bytes' => 1000,
    ]);

    $dokumen->update(['status_verifikasi' => 'diterima', 'diverifikasi_oleh_user_id' => $user->id]);

    expect(\Spatie\Activitylog\Models\Activity::where('log_name', 'dokumen_pendaftaran')->where('subject_id', $dokumen->id)->exists())->toBeTrue();
});
