<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new RolePermissionSeeder)->run();
});

it('renders sasaran and tarif section markers on the create page for a non-ppdb kategori default', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

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
    $user->assignRole('bendahara_lembaga');
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
    $user->assignRole('bendahara_lembaga');
    // Pin the tahun ajaran explicitly (same lembaga, fixed nama) instead of relying on
    // KelasFactory's default `tahun_ajaran_id => TahunAjaran::factory()` — that default creates
    // a TahunAjaran with its own random lembaga_id, which used to crash this page via the
    // TenantScope eager-load mismatch, and even now would make the expected label below
    // non-deterministic (random faker year).
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id, 'nama' => '2026/2027']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);

    $jenisTagihan = JenisTagihan::create(['lembaga_id' => $lembaga->id, 'nama' => 'SPP Kelas', 'kategori' => 'spp', 'bisa_dicicil' => false]);
    $grup = $jenisTagihan->sasaranGrup()->create(['tipe' => 'sasaran']);
    // Simulate value as originally saved from HTML form input: an array of strings.
    $grup->kriteria()->create(['field' => 'kelas', 'operator' => 'in', 'value' => [(string) $kelas->id]]);

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.edit', $jenisTagihan));

    $response->assertOk();

    // Blade's @js directive wraps the JSON payload as `JSON.parse('...')` via
    // Illuminate\Support\Js::from(), which double-json-encodes the value (once to produce the
    // JSON payload, once more to safely embed that string inside a single-quoted JS string
    // literal — escaping quotes to \u0022 AND doubling up already-escaped forward slashes, e.g.
    // "2026/2027" becomes \u0022...2026\\\/2027...\u0022). A naive single json_encode() plus a
    // manual quote-only replacement (the previous version of this helper) diverges the moment
    // the payload contains a "/" — as it now does via the tahun ajaran suffix in kelas labels
    // (e.g. "27A (2026/2027)"). Delegate to the real Js::from() so this always matches Blade's
    // actual output byte-for-byte, then strip the "JSON.parse('...')" wrapper to get just the
    // inner escaped JSON fragment (which is what actually appears embedded in the larger
    // referenceOptions/initialSasaran config blob in the rendered HTML).
    $asEmbeddedJs = function ($data) {
        $wrapped = (string) Js::from($data);

        return preg_replace('/^JSON\.parse\\(\'(.*)\'\\)$/s', '$1', $wrapped);
    };

    // The kriteria's stored value must appear as a string in the initialSasaran config blob.
    $kriteriaJson = $asEmbeddedJs(['field' => 'kelas', 'operator' => 'in', 'value' => [(string) $kelas->id]]);
    $response->assertSee($kriteriaJson, false);

    // The referenceOptions.kelas list must contain the matching option (as an integer id) so
    // the two sides of the Alpine `:selected` comparison (String(kriteria.value) vs
    // String(opt.value)) actually match after coercion. Label includes the tahun ajaran suffix
    // (the 2026-08-11 UI rework's disambiguation format — see JenisTagihanFormPageTest).
    $kelasOptionJson = $asEmbeddedJs([['value' => $kelas->id, 'label' => $kelas->nama.' (2026/2027)']]);
    $response->assertSee($kelasOptionJson, false);
});

it('shows human-readable labels for kriteria fields instead of raw keys', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // fieldLabels itself lives in the Vite-bundled JS (never inlined into the HTTP response,
    // and this codebase has no JS test tooling), so the verifiable surface via a Pest/Blade
    // test is that the Blade binding was actually swapped from the raw key to the lookup.
    $response->assertSee('x-text="fieldLabels[fieldOpt] ?? fieldOpt"', false);
});

it('explains the and/or relationship between kriteria rows and grup cards for both sasaran and tarif', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    // Sasaran and Tarif each state their own DAN rule with section-specific wording,
    // then their own ATAU rule (identical wording, since "grup" alone is unambiguous there).
    $response->assertSeeInOrder([
        'Semua kriteria di dalam satu grup harus terpenuhi bersamaan (DAN).',
        'Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).',
        'Semua kriteria di dalam satu grup tarif harus terpenuhi bersamaan (DAN).',
        'Setiap Grup adalah alternatif terpisah — siswa cukup cocok salah satu (ATAU).',
    ]);
});

it('explains that tarif grup cards are evaluated in priority order', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $user->assignRole('bendahara_lembaga');

    $response = $this->actingAs($user)->get(route('admin.jenis-tagihan.create'));

    $response->assertOk();
    $response->assertSee('Diproses berurutan dari atas — Grup pertama yang cocok dengan data siswa akan dipakai nominalnya.');
});
