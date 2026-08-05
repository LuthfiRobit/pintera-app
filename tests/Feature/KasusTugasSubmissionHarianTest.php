<?php
// tests/Feature/KasusTugasSubmissionHarianTest.php

use App\Models\Kasus;
use App\Models\KasusConsent;
use App\Models\KasusTugas;
use App\Models\KasusTugasSubmission;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;

function buatKasusDenganTugasHarianDanKontakUtama(Lembaga $lembaga): array
{
    [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBkUntukTugas($lembaga);

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
        'user_id' => $orangTuaUser->id, 'nama_lengkap' => 'Ibu Harian',
        'nik' => fake()->unique()->numerify('################'), 'no_hp' => '081200007777',
        'email' => 'ortu.harian@example.test',
    ]);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ibu', 'is_kontak_utama' => true]);

    $tugas = KasusTugas::factory()->create([
        'kasus_id' => $kasus->id,
        'frekuensi' => 'harian',
        'mulai_pada' => '2026-08-10',
        'batas_selesai_pada' => '2026-08-12',
    ]);

    KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'sesi_pendampingan', 'status' => 'disetujui', 'disetujui_at' => now()]);
    KasusConsent::create(['kasus_id' => $kasus->id, 'jenis' => 'pengumpulan_media']);

    return [$kasus, $tugas, $siswaUser, $orangTuaUser];
}

it('stores the submitted tanggal on a harian tugas submission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti hari kedua.',
        'tanggal' => '2026-08-11',
    ])->assertRedirect(route('kasus.show', $kasus));

    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->first();
    expect($submission->tanggal->toDateString())->toBe('2026-08-11');
});

it('rejects a tanggal outside the tugas mulai_pada-batas_selesai_pada range', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti di luar rentang.',
        'tanggal' => '2026-08-20',
    ])->assertSessionHasErrors('tanggal');

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->exists())->toBeFalse();
});

it('requires tanggal for a harian tugas submission', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Tanpa tanggal.',
    ])->assertSessionHasErrors('tanggal');
});

it('locks a date once its submission is menunggu_review, rejecting a second submission for the same date', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);
    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Submission pertama.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Percobaan kedua di tanggal sama.', 'tanggal' => '2026-08-10',
    ])->assertStatus(422);

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->where('tanggal', '2026-08-10')->count())->toBe(1);
});

it('lets orang tua kontak utama submit on behalf of the child for a specific date', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser, $orangTuaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);

    $this->actingAs($orangTuaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Dibantu ibu, hari pertama.', 'tanggal' => '2026-08-10',
    ])->assertRedirect(route('kasus.show', $kasus));

    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->first();
    expect($submission->tanggal->toDateString())->toBe('2026-08-10');
    expect($submission->orang_tua_id)->not->toBeNull();
    expect($submission->siswa_id)->toBeNull();
});

it('does not lock other dates when one date is locked', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);
    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Hari pertama.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Hari kedua.', 'tanggal' => '2026-08-11',
    ])->assertRedirect();

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->count())->toBe(2);
});

it('reopens only the revised date after a konselor requests revisi, and leaves tugas status alone', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasHarianDanKontakUtama($lembaga);
    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti hari pertama.', 'tanggal' => '2026-08-10',
    ])->assertRedirect();
    $submission = KasusTugasSubmission::where('tugas_id', $tugas->id)->where('tanggal', '2026-08-10')->firstOrFail();

    $konselorUser = User::where('id', '!=', $siswaUser->id)->whereHas('roles', fn ($q) => $q->where('name', 'guru'))->firstOrFail();
    Notification::fake();
    $this->actingAs($konselorUser)->patch(route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]), [
        'status_review' => 'revisi_diminta',
        'catatan_revisi' => 'Perbaiki bukti hari pertama.',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($tugas->refresh()->status->value)->toBe('dikerjakan');

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [
        'teks' => 'Bukti hari pertama, revisi.', 'tanggal' => '2026-08-10',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->where('tanggal', '2026-08-10')->count())->toBe(2);
});

it('still flips tugas status to revisi for a non-harian task, unchanged from before', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $konselorUser] = buatKasusDitugaskanKeGuruBk($lembaga);
    $tugas = KasusTugas::factory()->create(['kasus_id' => $kasus->id, 'status' => 'dikerjakan', 'frekuensi' => 'sekali']);
    $submission = KasusTugasSubmission::factory()->create(['tugas_id' => $tugas->id]);

    Notification::fake();
    $this->actingAs($konselorUser)->patch(route('kasus.tugas.submission.review', [$kasus, $tugas, $submission]), [
        'status_review' => 'revisi_diminta',
        'catatan_revisi' => 'Tolong lebih detail.',
    ])->assertRedirect(route('kasus.show', $kasus));

    expect($tugas->refresh()->status->value)->toBe('revisi');
});
