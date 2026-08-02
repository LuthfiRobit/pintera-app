<?php

use App\Models\EkstrakurikulerLembaga;
use App\Models\LayananKhususLembaga;
use App\Models\Lembaga;
use App\Models\LembagaDataPeriodik;
use App\Models\ProgramInklusiLembaga;
use App\Models\Role;
use App\Models\Semester;
use App\Models\User;
use App\Models\Yayasan;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['lembaga.view', 'lembaga.create', 'lembaga.edit'] as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
    }
    $this->role = Role::firstOrCreate(['name' => 'yayasan_admin', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $this->role->givePermissionTo(['lembaga.view', 'lembaga.edit']);
    
    $this->yayasan = Yayasan::factory()->create();
    $this->lembaga = Lembaga::factory()->create(['yayasan_id' => $this->yayasan->id]);
    
    $this->manager = User::factory()->create(['lembaga_id' => $this->lembaga->id]);
    $this->manager->assignRole($this->role);
});

it('can store, update, and delete ekstrakurikuler with hash redirect', function () {
    $storeResponse = $this->actingAs($this->manager)->post(route('admin.lembaga.ekstrakurikuler.store', $this->lembaga), [
        'jenis_ekskul' => 'olahraga',
        'nama_ekskul' => 'Futsal',
        'no_sk' => 'SK/EKS/001',
        'jam_per_minggu' => 2,
    ]);

    $storeResponse->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#ekstrakurikuler');
    expect(EkstrakurikulerLembaga::where('lembaga_id', $this->lembaga->id)->where('nama_ekskul', 'Futsal')->exists())->toBeTrue();

    $ekskul = EkstrakurikulerLembaga::where('nama_ekskul', 'Futsal')->first();
    
    $updateResponse = $this->actingAs($this->manager)->put(route('admin.lembaga.ekstrakurikuler.update', [$this->lembaga, $ekskul]), [
        'jenis_ekskul' => 'olahraga',
        'nama_ekskul' => 'Futsal Junior',
        'jam_per_minggu' => 4,
    ]);
    
    $updateResponse->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#ekstrakurikuler');
    expect($ekskul->refresh()->nama_ekskul)->toBe('Futsal Junior');

    $deleteResponse = $this->actingAs($this->manager)->delete(route('admin.lembaga.ekstrakurikuler.destroy', [$this->lembaga, $ekskul]));
    $deleteResponse->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#ekstrakurikuler');
    expect(EkstrakurikulerLembaga::find($ekskul->id))->toBeNull();
});

it('can manage layanan khusus with hash redirect', function () {
    $this->actingAs($this->manager)->post(route('admin.lembaga.layanan-khusus.store', $this->lembaga), [
        'jenis_layanan' => 'Bimbingan Konseling',
        'no_sk' => 'SK/BK/2026',
        'keterangan' => 'Layanan konseling aktif setiap hari kerja',
    ])->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#layanan-khusus');

    expect(LayananKhususLembaga::where('jenis_layanan', 'Bimbingan Konseling')->exists())->toBeTrue();
});

it('can manage program inklusi with hash redirect', function () {
    $this->actingAs($this->manager)->post(route('admin.lembaga.program-inklusi.store', $this->lembaga), [
        'kebutuhan_khusus' => 'Tuna Netra',
        'no_sk' => 'SK/INK/2026',
        'keterangan' => 'Didukung guru pendamping khusus',
    ])->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#program-inklusi');

    expect(ProgramInklusiLembaga::where('kebutuhan_khusus', 'Tuna Netra')->exists())->toBeTrue();
});

it('can manage data periodik with hash redirect', function () {
    $semester = Semester::factory()->create(['lembaga_id' => $this->lembaga->id]);
    $this->actingAs($this->manager)->post(route('admin.lembaga.data-periodik.store', $this->lembaga), [
        'semester_id' => $semester->id,
        'waktu_penyelenggaraan' => 'pagi',
        'sumber_listrik' => 'PLN',
        'daya_listrik' => 5500,
        'akses_internet' => 'Indihome 100Mbps',
        'status_bos' => 1,
    ])->assertRedirect(route('admin.lembaga.edit', $this->lembaga) . '#data-periodik');

    expect(LembagaDataPeriodik::where('lembaga_id', $this->lembaga->id)->where('semester_id', $semester->id)->exists())->toBeTrue();
});
