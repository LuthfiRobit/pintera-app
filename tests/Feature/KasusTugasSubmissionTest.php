<?php
// tests/Feature/KasusTugasSubmissionTest.php

use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

function buatKasusDenganTugasDanKontakUtama(Lembaga $lembaga): array
{
    [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBk($lembaga);

    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);

    $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $siswaRole = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaRole->givePermissionTo(['kasus.view']);
    $siswaUser->assignRole('siswa');
    $siswa->update(['user_id' => $siswaUser->id]);

    $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
    $orangTuaRole = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $orangTuaRole->givePermissionTo(['kasus.view']);
    $orangTuaUser->assignRole('orang_tua');
    $orangTua = OrangTua::create([
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Submission',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200008888',
        'email' => 'ortu.submission@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id]);

    KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan', 'status' => 'disetujui', 'disetujui_at' => now()]);
    KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media']);

    return [$kasus, $tugas, $siswaUser, $orangTuaUser];
}

it('lets siswa submit text-only evidence before media consent is approved', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Sudah saya kerjakan.',
    ])->assertRedirect(route('kasus.show', $kasus));

    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->first();
    expect($submission->teks)->toBe('Sudah saya kerjakan.');
    expect($submission->lampiran)->toBeNull();
    expect($submission->siswa_id)->not->toBeNull();
});

it('rejects lampiran on a submission when media consent is not yet approved', function () {
    Storage::fake('public');
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Ini bukti saya.',
        'lampiran' => UploadedFile::fake()->image('bukti.jpg'),
    ])->assertRedirect(route('kasus.show', $kasus));

    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->first();
    expect($submission->lampiran)->toBeNull();
});

it('accepts lampiran once media consent is approved', function () {
    Storage::fake('public');
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);
    KasusConsent::where('kasus_id', $kasus->id)->where('jenis', 'pengumpulan_media')
        ->update(['status' => 'disetujui', 'disetujui_at' => now()]);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Ini bukti saya.',
        'lampiran' => UploadedFile::fake()->image('bukti.jpg'),
    ])->assertRedirect(route('kasus.show', $kasus));

    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->first();
    expect($submission->lampiran)->not->toBeNull();
    Storage::disk('public')->assertExists($submission->lampiran);
});

it('lets orang tua kontak utama submit on behalf of the child', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, , $orangTuaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);

    $this->actingAs($orangTuaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Anak saya sudah mengerjakan.',
    ])->assertRedirect(route('kasus.show', $kasus));

    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->first();
    expect($submission->orang_tua_id)->not->toBeNull();
    expect($submission->siswa_id)->toBeNull();
});

it('auto-transitions tugas status from ditugaskan to dikerjakan on the first submission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);

    expect($tugas->status->value)->toBe('ditugaskan');

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), ['teks' => 'x']);

    expect($tugas->refresh()->status->value)->toBe('dikerjakan');
});

it('403s a siswa unrelated to the kasus from submitting', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas] = buatKasusDenganTugasDanKontakUtama($lembaga);

    $unrelatedSiswaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $siswaRole = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaRole->givePermissionTo(['kasus.view']);
    $unrelatedSiswaUser->assignRole('siswa');
    \App\Models\Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $unrelatedSiswaUser->id]);

    $this->actingAs($unrelatedSiswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), ['teks' => 'x'])
        ->assertForbidden();
});

it('creates a new submission row on resubmit rather than updating the old one', function () {
    // Spec requirement: kasus_tugas_submission rows are append-only history, never updated
    // in place, so a full record of every attempt survives even after revisions.
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), ['teks' => 'Percobaan pertama.']);
    $first = KasusTugasSubmission::where('tugas_id', $tugas->id)->first();

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), ['teks' => 'Percobaan kedua, setelah revisi.']);

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->count())->toBe(2);
    expect($first->refresh()->teks)->toBe('Percobaan pertama.');
});
