<?php

use App\Domains\Akademik\Enums\StatusRpp;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\Rpp;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

function siapkanRppKurikulumFixture(): array
{
    Permission::firstOrCreate(['name' => 'rpp.view', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'wakasek_kurikulum_reporting', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['rpp.view']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'status_aktif' => true]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'status_aktif' => true]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    $userKurikulum = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $userKurikulum->assignRole($role);
    $guru = Guru::factory()->create(['lembaga_id' => $lembaga->id]);

    $kelasMerdeka = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas Merdeka X', 'kurikulum' => 'merdeka']);
    $kelasK13 = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas K13 Y', 'kurikulum' => 'k13']);
    $kelasLegacy = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => 'Kelas Legacy Z', 'kurikulum' => null]);

    $rppMerdeka = Rpp::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelasMerdeka->id,
        'mata_pelajaran_id' => $mapel->id, 'judul_topik' => 'Topik RPP Merdeka Unik',
        'alokasi_waktu' => '2 JP', 'file_path' => 'rpp/fake-merdeka.pdf', 'file_name' => 'fake-merdeka.pdf',
        'file_size_bytes' => 1024, 'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);
    $rppK13 = Rpp::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelasK13->id,
        'mata_pelajaran_id' => $mapel->id, 'judul_topik' => 'Topik RPP K13 Unik',
        'alokasi_waktu' => '2 JP', 'file_path' => 'rpp/fake-k13.pdf', 'file_name' => 'fake-k13.pdf',
        'file_size_bytes' => 1024, 'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);
    $rppLegacy = Rpp::create([
        'yayasan_id' => $yayasan->id, 'lembaga_id' => $lembaga->id, 'guru_id' => $guru->id,
        'tahun_ajaran_id' => $tahunAjaran->id, 'semester_id' => $semester->id, 'kelas_id' => $kelasLegacy->id,
        'mata_pelajaran_id' => $mapel->id, 'judul_topik' => 'Topik RPP Legacy Unik',
        'alokasi_waktu' => '2 JP', 'file_path' => 'rpp/fake-legacy.pdf', 'file_name' => 'fake-legacy.pdf',
        'file_size_bytes' => 1024, 'mime_type' => 'application/pdf', 'status' => StatusRpp::Draft,
    ]);

    return [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy];
}

function chunkSekitarTeks(string $html, string $penanda): string
{
    $pos = strpos($html, $penanda);
    expect($pos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $pos), '<tr');
    expect($trOpenPos)->not->toBeFalse();

    return substr($html, $trOpenPos, ($pos - $trOpenPos) + 3000);
}

it('shows the correct kurikulum badge scoped to each RPP row, including legacy null', function () {
    [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy] = siapkanRppKurikulumFixture();

    // Existence dulu: ketiga RPP benar-benar tersimpan sebelum assert badge-nya.
    expect(Rpp::whereIn('id', [$rppMerdeka->id, $rppK13->id, $rppLegacy->id])->count())->toBe(3);

    $response = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya']));
    $response->assertOk();
    $html = $response->getContent();

    $chunkMerdeka = chunkSekitarTeks($html, 'Topik RPP Merdeka Unik');
    expect($chunkMerdeka)->toContain('Kurikulum Merdeka');

    $chunkK13 = chunkSekitarTeks($html, 'Topik RPP K13 Unik');
    expect($chunkK13)->toContain('Kurikulum 2013 (K13)');

    $chunkLegacy = chunkSekitarTeks($html, 'Topik RPP Legacy Unik');
    expect($chunkLegacy)->toContain('Belum Diketahui');
});

it('filters to only Merdeka RPP when kurikulum=merdeka, both on full-page and AJAX fragment requests', function () {
    [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy] = siapkanRppKurikulumFixture();

    $fullPage = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya', 'kurikulum' => 'merdeka']));
    $fullPage->assertOk();
    $fullPage->assertViewHas('rppList', fn ($list) => $list->pluck('id')->contains($rppMerdeka->id) && ! $list->pluck('id')->contains($rppK13->id));

    $ajax = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya', 'kurikulum' => 'merdeka']), [
        'X-Requested-With' => 'XMLHttpRequest',
    ]);
    $ajax->assertOk();
    $ajaxHtml = $ajax->getContent();
    expect($ajaxHtml)->toContain('Topik RPP Merdeka Unik');
    expect($ajaxHtml)->not->toContain('Topik RPP K13 Unik');
});

it('filters to only K13 RPP when kurikulum=k13', function () {
    [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy] = siapkanRppKurikulumFixture();

    $response = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya', 'kurikulum' => 'k13']));
    $response->assertOk();
    $response->assertViewHas('rppList', fn ($list) => $list->pluck('id')->contains($rppK13->id) && ! $list->pluck('id')->contains($rppMerdeka->id));
});

it('treats an unknown kurikulum value as no filter at all, without erroring', function () {
    [$userKurikulum, $rppMerdeka, $rppK13, $rppLegacy] = siapkanRppKurikulumFixture();

    $response = $this->actingAs($userKurikulum)->get(route('admin.rpp.index', ['tab' => 'saya', 'kurikulum' => 'foobar']));

    $response->assertOk();
    $response->assertViewHas('rppList', fn ($list) => $list->pluck('id')->contains($rppMerdeka->id) && $list->pluck('id')->contains($rppK13->id) && $list->pluck('id')->contains($rppLegacy->id));
});
