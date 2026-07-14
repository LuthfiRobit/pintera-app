<?php

use App\Models\CalonMurid;
use App\Models\GelombangPpdb;
use App\Models\JalurPpdb;
use App\Models\Lembaga;
use App\Models\Pendaftaran;
use App\Models\SkPpdb;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;

function buatPendaftaranUntukBukti(string $status, bool $dengan_sk): Pendaftaran
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
        'kode_pendaftaran' => 'REG-2026-'.random_int(10000, 99999), 'email_pendaftaran' => 'wali@example.test',
        'status' => $status, 'submitted_at' => now(),
    ]);

    if ($dengan_sk) {
        $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $sk = SkPpdb::create([
            'gelombang_ppdb_id' => $gelombang->id, 'lembaga_id' => $lembaga->id,
            'nomor_sk' => '421.3/SK-PPDB.001/2026', 'tanggal_terbit' => now()->toDateString(),
            'diterbitkan_oleh_user_id' => $user->id, 'file_path' => 'sk/1.pdf',
        ]);
        $pendaftaran->update(['sk_ppdb_id' => $sk->id]);
    }

    return $pendaftaran->fresh();
}

it('shows the sk reference line when the pendaftaran is linked to a sk_ppdb', function () {
    $pendaftaran = buatPendaftaranUntukBukti('diterima', dengan_sk: true);

    $html = view('pdf.bukti-pendaftaran', ['lembaga' => $pendaftaran->lembaga, 'pendaftaran' => $pendaftaran])->render();

    expect($html)->toContain('421.3/SK-PPDB.001/2026');
    expect($html)->toContain('Ditetapkan berdasarkan SK');
});

it('does not show the sk reference line when the decision is final but no sk has been issued yet', function () {
    $pendaftaran = buatPendaftaranUntukBukti('diterima', dengan_sk: false);

    $html = view('pdf.bukti-pendaftaran', ['lembaga' => $pendaftaran->lembaga, 'pendaftaran' => $pendaftaran])->render();

    expect($html)->not->toContain('Ditetapkan berdasarkan SK');
});

it('does not show the sk reference line while still menunggu_verifikasi', function () {
    $pendaftaran = buatPendaftaranUntukBukti('menunggu_verifikasi', dengan_sk: false);

    $html = view('pdf.bukti-pendaftaran', ['lembaga' => $pendaftaran->lembaga, 'pendaftaran' => $pendaftaran])->render();

    expect($html)->not->toContain('Ditetapkan berdasarkan SK');
});
