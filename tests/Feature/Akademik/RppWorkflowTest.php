<?php

declare(strict_types=1);

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Storage::fake('public');

    Permission::firstOrCreate(['name' => 'rpp.view', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rpp.kelola', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'rpp.verify', 'guard_name' => 'web']);

    $roleGuru = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $roleGuru->givePermissionTo(['rpp.view', 'rpp.kelola']);

    $roleKurikulum = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $roleKurikulum->givePermissionTo(['rpp.view', 'rpp.kelola', 'rpp.verify']);

    $this->yayasan = Yayasan::factory()->create();
    $this->lembaga = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    $this->tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $this->lembaga->id, 'status_aktif' => true]);
    $this->semester = Semester::factory()->create(['tahun_ajaran_id' => $this->tahunAjaran->id, 'status_aktif' => true]);
    $this->kelas = Kelas::factory()->create(['lembaga_id' => $this->lembaga->id, 'tahun_ajaran_id' => $this->tahunAjaran->id]);
    $this->mapel = MataPelajaran::factory()->create(['lembaga_id' => $this->lembaga->id, 'nama' => 'Pendidikan Agama Islam']);

    // User Guru
    $this->userGuru = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
    $this->userGuru->assignRole($roleGuru);
    $this->guru = Guru::factory()->create(['user_id' => $this->userGuru->id, 'lembaga_id' => $this->lembaga->id, 'nama' => 'Ustadzah Maya']);

    // User Kurikulum / Kepsek
    $this->userKurikulum = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
    $this->userKurikulum->assignRole($roleKurikulum);
});

it('menolak akses tanpa permission rpp.view', function () {
    $userPolos = User::factory()->create();
    $this->actingAs($userPolos)->get(route('admin.rpp.index'))->assertForbidden();
});

it('berhasil mengunggah dokumen RPP baru sebagai draf', function () {
    $file = UploadedFile::fake()->create('modul_ajar_aljabar.pdf', 500, 'application/pdf');

    $response = $this->actingAs($this->userGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $this->kelas->id,
        'semester_id' => $this->semester->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'judul_topik' => 'Operasi Hitung Aljabar',
        'alokasi_waktu' => '2 x 35 Menit',
        'pertemuan_ke' => '1',
        'file' => $file,
    ]);

    $response->assertRedirect(route('admin.rpp.index', ['tab' => 'saya']));

    $rpp = Rpp::first();
    expect($rpp)->not->toBeNull()
        ->and($rpp->status)->toBe(StatusRpp::Draft)
        ->and($rpp->judul_topik)->toBe('Operasi Hitung Aljabar')
        ->and($rpp->guru_id)->toBe($this->guru->id)
        ->and($rpp->file_name)->toBe('modul_ajar_aljabar.pdf');

    Storage::disk('public')->assertExists($rpp->file_path);
});

it('mendukung upload RPP tematik PAUD tanpa mata pelajaran', function () {
    $file = UploadedFile::fake()->create('rpph_sentra_bahan_alam.docx', 300, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $response = $this->actingAs($this->userGuru)->post(route('admin.rpp.store'), [
        'kelas_id' => $this->kelas->id,
        'semester_id' => $this->semester->id,
        'mata_pelajaran_id' => null,
        'judul_topik' => 'Eksplorasi Alam & Dedaunan',
        'alokasi_waktu' => '1 Pekan',
        'file' => $file,
    ]);

    $response->assertRedirect();
    $rpp = Rpp::first();
    expect($rpp->mata_pelajaran_id)->toBeNull()
        ->and($rpp->judul_topik)->toBe('Eksplorasi Alam & Dedaunan');
});

it('mengizinkan guru mengajukan RPP ke kurikulum dan mengunci dari edit', function () {
    $file = UploadedFile::fake()->create('rpp_ipa.pdf', 200, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');

    $rpp = Rpp::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester_id' => $this->semester->id,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'judul_topik' => 'Ekosistem Laut',
        'alokasi_waktu' => '2 JP',
        'file_path' => $path,
        'file_name' => 'rpp_ipa.pdf',
        'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf',
        'status' => StatusRpp::Draft,
    ]);

    // Guru ajukan RPP
    $response = $this->actingAs($this->userGuru)->post(route('admin.rpp.submit', $rpp));
    $response->assertRedirect();

    expect($rpp->fresh()->status)->toBe(StatusRpp::Diajukan);

    // Guru mencoba mengedit RPP yang sedang diajukan -> harus ditolak
    $updateResponse = $this->actingAs($this->userGuru)->put(route('admin.rpp.update', $rpp), [
        'kelas_id' => $this->kelas->id,
        'judul_topik' => 'Ekosistem Hutan',
        'alokasi_waktu' => '2 JP',
    ]);

    $updateResponse->assertSessionHasErrors('status');
    expect($rpp->fresh()->judul_topik)->toBe('Ekosistem Laut');
});

it('mengizinkan verifikator menyetujui RPP diajukan', function () {
    $file = UploadedFile::fake()->create('rpp_matematika.pdf', 200, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');

    $rpp = Rpp::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester_id' => $this->semester->id,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'judul_topik' => 'Trigonometri',
        'alokasi_waktu' => '4 JP',
        'file_path' => $path,
        'file_name' => 'rpp_matematika.pdf',
        'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf',
        'status' => StatusRpp::Diajukan,
    ]);

    // Kurikulum approve
    $response = $this->actingAs($this->userKurikulum)->post(route('admin.rpp.verify', $rpp), [
        'status' => 'disetujui',
    ]);

    $response->assertRedirect();
    $rppFresh = $rpp->fresh();

    expect($rppFresh->status)->toBe(StatusRpp::Disetujui)
        ->and($rppFresh->verified_by_user_id)->toBe($this->userKurikulum->id)
        ->and($rppFresh->verified_at)->not->toBeNull();
});

it('mengizinkan verifikator meminta revisi dengan catatan wajib', function () {
    $file = UploadedFile::fake()->create('rpp_biologi.pdf', 200, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');

    $rpp = Rpp::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester_id' => $this->semester->id,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'judul_topik' => 'Genetika',
        'alokasi_waktu' => '2 JP',
        'file_path' => $path,
        'file_name' => 'rpp_biologi.pdf',
        'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf',
        'status' => StatusRpp::Diajukan,
    ]);

    // Kurikulum meminta revisi tanpa catatan -> gagal validasi
    $failedResponse = $this->actingAs($this->userKurikulum)->post(route('admin.rpp.verify', $rpp), [
        'status' => 'perlu_revisi',
        'catatan_revisi' => '',
    ]);
    $failedResponse->assertSessionHasErrors('catatan_revisi');

    // Kurikulum meminta revisi dengan catatan yang jelas
    $response = $this->actingAs($this->userKurikulum)->post(route('admin.rpp.verify', $rpp), [
        'status' => 'perlu_revisi',
        'catatan_revisi' => 'Mohon tambahkan rubrik asesmen formatif di lampiran.',
    ]);

    $response->assertRedirect();
    $rppFresh = $rpp->fresh();

    expect($rppFresh->status)->toBe(StatusRpp::PerluRevisi)
        ->and($rppFresh->catatan_revisi)->toBe('Mohon tambahkan rubrik asesmen formatif di lampiran.');

    // Guru sekarang bisa mengedit kembali dan mengajukan ulang
    $updateResponse = $this->actingAs($this->userGuru)->put(route('admin.rpp.update', $rpp), [
        'kelas_id' => $this->kelas->id,
        'judul_topik' => 'Genetika & Pewarisan Sifat Lengkap',
        'alokasi_waktu' => '2 JP',
    ]);
    $updateResponse->assertRedirect();
    expect($rpp->fresh()->judul_topik)->toBe('Genetika & Pewarisan Sifat Lengkap');
});

it('mengisolasi data RPP per lembaga multi-tenant', function () {
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    $userLembagaLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $userLembagaLain->givePermissionTo(['rpp.view', 'rpp.verify']);

    $file = UploadedFile::fake()->create('rpp_sd.pdf', 200, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');

    $rppLembaga1 = Rpp::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester_id' => $this->semester->id,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'judul_topik' => 'RPP Lembaga 1',
        'alokasi_waktu' => '2 JP',
        'file_path' => $path,
        'file_name' => 'rpp_sd.pdf',
        'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf',
        'status' => StatusRpp::Diajukan,
    ]);

    // User lembaga lain mengakses inbox verifikasi
    $response = $this->actingAs($userLembagaLain)->get(route('admin.rpp.index', ['tab' => 'verifikasi']));
    $response->assertOk();
    $rppList = $response->viewData('rppList');

    // RPP lembaga 1 tidak boleh muncul di inbox lembaga lain
    expect($rppList->pluck('id'))->not->toContain($rppLembaga1->id);
});

it('berhasil mengunduh berkas fisik RPP', function () {
    $file = UploadedFile::fake()->create('dokumen_resmi.pdf', 100, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');

    $rpp = Rpp::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester_id' => $this->semester->id,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'judul_topik' => 'Dokumen Resmi',
        'alokasi_waktu' => '1 JP',
        'file_path' => $path,
        'file_name' => 'dokumen_resmi.pdf',
        'file_size_bytes' => 1024,
        'mime_type' => 'application/pdf',
        'status' => StatusRpp::Draft,
    ]);

    $response = $this->actingAs($this->userGuru)->get(route('admin.rpp.download', $rpp));
    $response->assertOk();

    // Verifikasi inline preview response
    $responseInline = $this->actingAs($this->userGuru)->get(route('admin.rpp.download', ['rpp' => $rpp, 'inline' => 1]));
    $responseInline->assertOk();
    expect($responseInline->headers->get('Content-Disposition'))->toContain('inline');
});

it('mencegah user lembaga lain mengakses RPP milik lembaga lain via rute single-resource', function () {
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    $userLembagaLain = User::factory()->create(['lembaga_id' => $lembagaLain->id]);
    $userLembagaLain->givePermissionTo(['rpp.view', 'rpp.kelola', 'rpp.verify']);

    $file = UploadedFile::fake()->create('rpp_rahasia.pdf', 200, 'application/pdf');
    $path = $file->store("rpp/{$this->lembaga->id}", 'public');

    $rpp = Rpp::create([
        'yayasan_id' => $this->yayasan->id,
        'lembaga_id' => $this->lembaga->id,
        'guru_id' => $this->guru->id,
        'tahun_ajaran_id' => $this->tahunAjaran->id,
        'semester_id' => $this->semester->id,
        'kelas_id' => $this->kelas->id,
        'mata_pelajaran_id' => $this->mapel->id,
        'judul_topik' => 'RPP Rahasia Lembaga 1',
        'alokasi_waktu' => '2 JP',
        'file_path' => $path,
        'file_name' => 'rpp_rahasia.pdf',
        'file_size_bytes' => 2048,
        'mime_type' => 'application/pdf',
        'status' => StatusRpp::Diajukan,
    ]);

    $this->actingAs($userLembagaLain)->get(route('admin.rpp.download', $rpp))->assertNotFound();

    $this->actingAs($userLembagaLain)->put(route('admin.rpp.update', $rpp), [
        'kelas_id' => $this->kelas->id,
        'judul_topik' => 'Diubah Paksa',
        'alokasi_waktu' => '2 JP',
    ])->assertNotFound();

    $this->actingAs($userLembagaLain)->post(route('admin.rpp.verify', $rpp), [
        'status' => 'disetujui',
    ])->assertNotFound();

    $this->actingAs($userLembagaLain)->delete(route('admin.rpp.destroy', $rpp))->assertNotFound();

    expect($rpp->fresh())->not->toBeNull()
        ->and($rpp->fresh()->judul_topik)->toBe('RPP Rahasia Lembaga 1');
});
