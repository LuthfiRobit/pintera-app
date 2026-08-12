<?php

use App\Models\JenisTagihan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders sasaran and tarif section markers on the create page for a non-ppdb kategori default', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // The 2026-08-11 "gold standard" UI rework dropped the numbered-step prefixes from section
    // titles (see .agents/logs/2026-08-11-jenis-tagihan-ui-ux.md) — assert on the current text.
    $response->assertSee('Target Sasaran');
    $response->assertSee('Tarif Berdimensi');
});

it('pre-fills sasaran kriteria fields from an existing jenis tagihan on the edit page', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Bulanan', 'kategori' => 'spp', 'bisa_dicicil' => false]);
    $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
    $grup->kriteria()->create(['field' => 'status_siswa', 'operator' => 'in', 'value' => ['aktif']]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();
    $response->assertSee('status_siswa', false);
});

it('exposes both the stored kriteria value and the matching reference option id for an id-valued kriteria field', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id]);

    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Kelas', 'kategori' => 'spp', 'bisa_dicicil' => false]);
    $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
    // Simulate value as originally saved from HTML form input: an array of strings.
    $grup->kriteria()->create(['field' => 'kelas', 'operator' => 'in', 'value' => [(string) $kelas->id]]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();

    // Blade's @js directive wraps the JSON payload as `JSON.parse('...')`, which requires a
    // second encoding pass that turns every literal double quote into the six-character
    // sequence " (so it doesn't collide with the surrounding single-quoted JS string).
    // Mirror that here so the expected fragments match the raw HTML byte-for-byte.
    $asEmbeddedJs = fn ($data) => str_replace('"', chr(92).chr(117).chr(48).chr(48).chr(50).chr(50), json_encode($data));

    // The kriteria's stored value must appear as a string in the initialSasaran config blob.
    $kriteriaJson = $asEmbeddedJs(['field' => 'kelas', 'operator' => 'in', 'value' => [(string) $kelas->id]]);
    $response->assertSee($kriteriaJson, false);

    // The referenceOptions.kelas list must contain the matching option (as an integer id) so
    // the two sides of the Alpine `:selected` comparison (String(kriteria.value) vs
    // String(opt.value)) actually match after coercion.
    $kelasOptionJson = $asEmbeddedJs([['value' => $kelas->id, 'label' => $kelas->nama]]);
    $response->assertSee($kelasOptionJson, false);
});

it('shows human-readable labels for kriteria fields instead of raw keys', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('admin_keuangan');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // fieldLabels itself lives in the Vite-bundled JS (never inlined into the HTTP response,
    // and this codebase has no JS test tooling), so the verifiable surface via a Pest/Blade
    // test is that the Blade binding was actually swapped from the raw key to the lookup.
    $response->assertSee('x-text="fieldLabels[fieldOpt] ?? fieldOpt"', false);
});
