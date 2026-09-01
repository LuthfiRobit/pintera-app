<?php

use App\Domains\Keuangan\Models\JenisTagihan;
use App\Domains\Keuangan\Models\JenisTagihanSasaranGrup;
use App\Domains\Keuangan\Services\JenisTagihanSasaranMatcher;
use App\Domains\Keuangan\Services\TagihanNominalResolver;
use App\Models\Lembaga;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;

it('backfills priority for existing tarif grups matching their original id order, per jenis_tagihan_id', function () {
    $jenisTagihanA = JenisTagihan::factory()->create();
    $jenisTagihanB = JenisTagihan::factory()->create();

    // Simulasikan baris "lama" (sebelum migrasi ini) dengan insert langsung, priority belum diisi.
    $grupA1 = DB::table('jenis_tagihan_sasaran_grup')->insertGetId(['jenis_tagihan_id' => $jenisTagihanA->id, 'tipe' => 'tarif', 'nominal' => 100000, 'created_at' => now(), 'updated_at' => now()]);
    $grupA2 = DB::table('jenis_tagihan_sasaran_grup')->insertGetId(['jenis_tagihan_id' => $jenisTagihanA->id, 'tipe' => 'tarif', 'nominal' => 200000, 'created_at' => now(), 'updated_at' => now()]);
    $grupB1 = DB::table('jenis_tagihan_sasaran_grup')->insertGetId(['jenis_tagihan_id' => $jenisTagihanB->id, 'tipe' => 'tarif', 'nominal' => 300000, 'created_at' => now(), 'updated_at' => now()]);

    DB::statement('
        UPDATE jenis_tagihan_sasaran_grup g
        JOIN (SELECT id, ROW_NUMBER() OVER (PARTITION BY jenis_tagihan_id ORDER BY id) AS rn FROM jenis_tagihan_sasaran_grup WHERE tipe = "tarif") ranked
        ON g.id = ranked.id
        SET g.priority = ranked.rn
    ');

    expect(JenisTagihanSasaranGrup::find($grupA1)->priority)->toBe(1);
    expect(JenisTagihanSasaranGrup::find($grupA2)->priority)->toBe(2);
    expect(JenisTagihanSasaranGrup::find($grupB1)->priority)->toBe(1); // partition terpisah per jenis_tagihan_id
});

it('resolveNominal picks the tarif grup with the lowest priority that matches, not insertion order', function () {
    $lembaga = Lembaga::factory()->create();
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);
    $jenisTagihan = JenisTagihan::factory()->create(['lembaga_id' => $lembaga->id, 'default_amount' => 999999]);

    $grupUmum = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 100000, 'priority' => 2]);
    $grupSpesifik = $jenisTagihan->sasaranGrup()->create(['tipe' => 'tarif', 'nominal' => 250000, 'priority' => 1]);
    // Kedua grup dibuat tanpa kriteria sama sekali -> siswaMatchesGrup() true untuk keduanya (AND kosong = true).

    $matcher = new JenisTagihanSasaranMatcher;
    $resolver = new TagihanNominalResolver($matcher);
    $result = $resolver->resolve($siswa, $jenisTagihan);

    expect($result['nominal'])->toBe(250000.0); // grupSpesifik (priority 1) menang meski dibuat SETELAH grupUmum
});
