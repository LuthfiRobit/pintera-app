<?php

use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
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
