<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Js;
use Spatie\Permission\Models\Permission;

function siapkanKenaikanKelasUxUser(): array
{
    Permission::firstOrCreate(['name' => 'kenaikan-kelas.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'operator_kenaikan_ux', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kenaikan-kelas.kelola']);

    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return [$manager, $lembaga];
}

/**
 * Ambil substring HTML utk satu <select> spesifik lewat atribut `name`-nya.
 * Proyek ini tidak punya symfony/dom-crawler terinstal, jadi scoping HTML
 * dilakukan manual via regex non-greedy, bukan lewat $response->getCrawler().
 */
function htmlSelectByName(string $html, string $selectName): string
{
    $pattern = '/<select[^>]*name="'.preg_quote($selectName, '/').'"[^>]*>.*?<\/select>/s';
    preg_match($pattern, $html, $matches);

    return $matches[0] ?? '';
}

function selectedOptionValue(string $selectHtml): ?string
{
    preg_match('/<option[^>]*value="([^"]*)"[^>]*selected/s', $selectHtml, $matches);

    return $matches[1] ?? null;
}

it('pre-selects Lulus for a kelas at the terminal tingkat of its jenjang', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasTingkatAkhir = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => 'Kelas 6 Akhir', 'tingkat' => '6']);
    $kelasBukanTingkatAkhir = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => 'Kelas 3 Biasa', 'tingkat' => '3']);
    // Kelas tidak boleh kosong (siswa_count = 0), kalau tidak default tindakan akan "lewati", bukan naik/lulus.
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasTingkatAkhir->id]);
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelasBukanTingkatAkhir->id]);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $response->assertOk();
    $html = $response->getContent();

    $selectTingkatAkhir = htmlSelectByName($html, "mapping[{$kelasTingkatAkhir->id}][tindakan]");
    $selectBukanTingkatAkhir = htmlSelectByName($html, "mapping[{$kelasBukanTingkatAkhir->id}][tindakan]");

    expect($selectTingkatAkhir)->not->toBe('');
    expect($selectBukanTingkatAkhir)->not->toBe('');
    expect(selectedOptionValue($selectTingkatAkhir))->toBe('lulus');
    expect(selectedOptionValue($selectBukanTingkatAkhir))->toBe('naik');
});

it('renders the kurikulum-asal value and matching data-kurikulum options for the JS warning to compare', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id,
        'nama' => 'Kelas Asal K13', 'tingkat' => '3', 'kurikulum' => 'k13',
    ]);
    $kelasTujuanBerbeda = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id,
        'nama' => 'Kelas Tujuan Merdeka', 'tingkat' => '4', 'kurikulum' => 'merdeka',
    ]);
    $kelasTujuanSama = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id,
        'nama' => 'Kelas Tujuan K13 Juga', 'tingkat' => '4', 'kurikulum' => 'k13',
    ]);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $response->assertOk();
    $html = $response->getContent();

    // Existence dulu: kedua kelas tujuan benar-benar muncul di dropdown sebelum assert data attribute-nya.
    expect($html)->toContain('Kelas Tujuan Merdeka');
    expect($html)->toContain('Kelas Tujuan K13 Juga');

    // Server/Blade contract: kurikulum asal ter-serialize benar di x-data baris kelas ini.
    // Cari posisi <tr sungguhan sebelum nama kelas, BUKAN offset karakter tebakan --
    // atribut x-data berisi beberapa baris JS + indentasi Blade, bisa >400 karakter.
    $namaPos = strpos($html, 'Kelas Asal K13');
    expect($namaPos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $namaPos), '<tr');
    expect($trOpenPos)->not->toBeFalse();
    $trChunk = substr($html, $trOpenPos, ($namaPos - $trOpenPos) + 3000);
    expect($trChunk)->toContain('kurikulumAsal');
    expect($trChunk)->toContain('k13');

    // data-kurikulum pada option kelas tujuan sesuai nilai kurikulum sungguhan.
    expect($trChunk)->toContain('data-kurikulum="merdeka"');
    expect($trChunk)->toContain('data-kurikulum="k13"');

    // Expression perbandingan warning ada di markup (bukan typo/operator salah).
    expect($trChunk)->toContain('kurikulumTujuan !== null');
    expect($trChunk)->toContain('kurikulumAsal !== null');
    expect($trChunk)->toContain('kurikulumTujuan !== kurikulumAsal');
});

it('renders kurikulumAsal as null in x-data when the source kelas has no kurikulum value', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLamaTanpaKurikulum = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id,
        'nama' => 'Kelas Legacy Tanpa Kurikulum', 'tingkat' => '2', 'kurikulum' => null,
    ]);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $response->assertOk();
    $html = $response->getContent();

    $namaPos = strpos($html, 'Kelas Legacy Tanpa Kurikulum');
    expect($namaPos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $namaPos), '<tr');
    expect($trOpenPos)->not->toBeFalse();
    $trChunk = substr($html, $trOpenPos, ($namaPos - $trOpenPos) + 3000);
    expect($trChunk)->toContain('kurikulumAsal');
    expect($trChunk)->toContain('null');
});

it('pre-selects Lewati for a kelas that is already empty, instead of forcing naik/lulus', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasKosong = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'nama' => 'Kelas Kosong', 'tingkat' => '3']);
    // Sengaja tidak buat Siswa -> siswa_count = 0

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $response->assertOk();
    $html = $response->getContent();

    $select = htmlSelectByName($html, "mapping[{$kelasKosong->id}][tindakan]");
    expect($select)->not->toBe('');
    expect(selectedOptionValue($select))->toBe('lewati');
});

it('memproses mapping massal tanpa error meski ada kelas kosong yang dilewati', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasKosong = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id, 'tingkat' => '3']);
    $kelasBaru = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'tingkat' => '4']);

    $response = $this->actingAs($manager)->post(route('admin.kenaikan-kelas.store'), [
        'mapping' => [
            $kelasKosong->id => ['tindakan' => 'lewati', 'kelas_baru_id' => ''],
        ],
    ]);

    $response->assertSessionDoesntHaveErrors();
    $response->assertRedirect(route('admin.kelas.index'));
});

it('wires up index-based tingkat comparison data for a numeric jenjang (SD)', function () {
    [$manager, $lembaga] = siapkanKenaikanKelasUxUser();
    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelasLama = Kelas::factory()->create([
        'lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunLalu->id,
        'nama' => 'Kelas Tingkat 3', 'tingkat' => '3',
    ]);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Kelas Tingkat 2', 'tingkat' => '2']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Kelas Tingkat 4', 'tingkat' => '4']);
    Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Kelas Tingkat 6', 'tingkat' => '6']);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $response->assertOk();
    $html = $response->getContent();

    $namaPos = strpos($html, 'Kelas Tingkat 3');
    expect($namaPos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $namaPos), '<tr');
    expect($trOpenPos)->not->toBeFalse();
    $trChunk = substr($html, $trOpenPos, ($namaPos - $trOpenPos) + 3000);

    expect($trChunk)->toContain('tingkatAsal: '.Js::from('3'));
    expect($trChunk)->toContain('daftarTingkat: '.Js::from(['1', '2', '3', '4', '5', '6']));
    expect($trChunk)->toContain('data-tingkat="2"');
    expect($trChunk)->toContain('data-tingkat="4"');
    expect($trChunk)->toContain('data-tingkat="6"');
    expect($html)->toContain('get selisihIndexTingkat()');
    expect($html)->toContain('↔ Tinggal kelas: tingkat tidak berubah');
    expect($html)->toContain('⚠ Tingkat tidak wajar: dari tingkat');
});

it('wires up index-based tingkat comparison data for an alphabetic jenjang (TK), proving it is not numeric arithmetic', function () {
    $yayasan = Yayasan::factory()->create();
    $lembagaTk = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'TK']);
    Permission::firstOrCreate(['name' => 'kenaikan-kelas.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'operator_kenaikan_ux_tk', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['kenaikan-kelas.kelola']);
    $manager = User::factory()->create(['lembaga_id' => $lembagaTk->id]);
    $manager->assignRole($role);

    $tahunLalu = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTk->id]);
    $tahunBaru = TahunAjaran::factory()->create(['lembaga_id' => $lembagaTk->id]);
    $kelasLama = Kelas::factory()->create([
        'lembaga_id' => $lembagaTk->id, 'tahun_ajaran_id' => $tahunLalu->id,
        'nama' => 'Kelas TK A Uji', 'tingkat' => 'A',
    ]);
    Kelas::factory()->create(['lembaga_id' => $lembagaTk->id, 'tahun_ajaran_id' => $tahunBaru->id, 'nama' => 'Kelas TK B Uji', 'tingkat' => 'B']);

    $response = $this->actingAs($manager)->get(route('admin.kenaikan-kelas.index', [
        'tahun_ajaran_id' => $tahunLalu->id,
        'tahun_ajaran_tujuan_id' => $tahunBaru->id,
    ]));
    $response->assertOk();
    $html = $response->getContent();

    $namaPos = strpos($html, 'Kelas TK A Uji');
    expect($namaPos)->not->toBeFalse();
    $trOpenPos = strrpos(substr($html, 0, $namaPos), '<tr');
    expect($trOpenPos)->not->toBeFalse();
    $trChunk = substr($html, $trOpenPos, ($namaPos - $trOpenPos) + 3000);

    expect($trChunk)->toContain('tingkatAsal: '.Js::from('A'));
    expect($trChunk)->toContain('daftarTingkat: '.Js::from(['A', 'B']));
    expect($trChunk)->toContain('data-tingkat="B"');
});
