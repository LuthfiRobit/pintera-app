<?php

use App\Domains\Akademik\Models\Presensi;
use App\Domains\Akademik\Models\SesiPembelajaran;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\WhatsAppTemplate;
use App\Models\Yayasan;
use App\Notifications\Akademik\PresensiPengecualianNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('toDatabase berisi nama siswa, status label, dan tanggal', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Ahmad Fauzi']);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-09-10']);
    $presensi = Presensi::factory()->create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'sakit', 'keterangan' => 'Demam tinggi']);

    $data = (new PresensiPengecualianNotification($presensi))->toDatabase(null);

    expect($data['presensi_id'])->toBe($presensi->id);
    expect($data['message'])->toContain('Ahmad Fauzi');
    expect($data['message'])->toContain('Sakit');
});

it('toWhatsApp merender template dengan nama, status, tanggal, dan keterangan', function () {
    WhatsAppTemplate::factory()->create([
        'kode' => 'presensi_pengecualian',
        'isi_template' => 'Yth. Orang Tua {nama_siswa}, presensi tercatat {status} pada {tanggal}. Keterangan: {keterangan}.',
    ]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'nama_lengkap' => 'Bunga Lestari']);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id, 'tanggal' => '2026-09-10']);
    $presensi = Presensi::factory()->create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'izin', 'keterangan' => 'Acara keluarga']);

    $pesan = (new PresensiPengecualianNotification($presensi))->toWhatsApp(null);

    expect($pesan)->toContain('Bunga Lestari');
    expect($pesan)->toContain('Izin');
    expect($pesan)->toContain('Acara keluarga');
});

it('toWhatsApp menampilkan tanda strip kalau keterangan kosong', function () {
    WhatsAppTemplate::factory()->create([
        'kode' => 'presensi_pengecualian',
        'isi_template' => 'Keterangan: {keterangan}.',
    ]);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id]);
    $sesi = SesiPembelajaran::factory()->create(['kelas_id' => $kelas->id]);
    $presensi = Presensi::factory()->create(['sesi_pembelajaran_id' => $sesi->id, 'siswa_id' => $siswa->id, 'status' => 'alpa', 'keterangan' => null]);

    $pesan = (new PresensiPengecualianNotification($presensi))->toWhatsApp(null);

    expect($pesan)->toContain('Keterangan: -.');
});
