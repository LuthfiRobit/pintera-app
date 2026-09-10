<?php

use App\Domains\Akademik\Enums\StatusPengajuanRapor;
use App\Domains\Akademik\Models\PengajuanRapor;
use App\Models\Kelas;
use App\Models\Semester;
use App\Models\User;
use Spatie\Permission\Models\Permission;

it('menampilkan pengajuan yang sudah Disetujui di tab riwayat, bukan tab default', function () {
    $kelas = Kelas::factory()->create();
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $kelas->tahun_ajaran_id]);

    $pengajuanDisetujui = PengajuanRapor::create([
        'kelas_id' => $kelas->id,
        'semester_id' => $semester->id,
        'lembaga_id' => $kelas->lembaga_id,
        'status' => StatusPengajuanRapor::Disetujui,
        'diajukan_pada' => now(),
    ]);

    Permission::firstOrCreate(['name' => 'rapor.approve', 'guard_name' => 'web']);

    $user = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $user->givePermissionTo('rapor.approve');

    $responseDefault = $this->actingAs($user)->get(route('admin.rapor.persetujuan.index'));
    $responseDefault->assertDontSee($kelas->nama);

    $responseRiwayat = $this->actingAs($user)->get(route('admin.rapor.persetujuan.index', ['tab' => 'riwayat']));
    $responseRiwayat->assertSee($kelas->nama);
});

it('shows a riwayat-specific empty state message on the riwayat tab, not the menunggu-keputusan wording', function () {
    Permission::firstOrCreate(['name' => 'rapor.approve', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->givePermissionTo('rapor.approve');

    $response = $this->actingAs($user)->get(route('admin.rapor.persetujuan.index', ['tab' => 'riwayat']));

    $response->assertOk();
    $response->assertSee('Belum ada riwayat keputusan persetujuan rapor.');
    $response->assertDontSee('Tidak ada pengajuan rapor yang menunggu keputusan Anda saat ini.');
});
