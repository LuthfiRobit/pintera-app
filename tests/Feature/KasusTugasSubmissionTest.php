<?php

// tests/Feature/KasusTugasSubmissionTest.php

use App\Domains\Kasus\Enums\StatusKasus;
use App\Domains\Kasus\Models\Kasus;
use App\Domains\Kasus\Models\KasusConsent;
use App\Domains\Kasus\Models\KasusTugas;
use App\Domains\Kasus\Models\KasusTugasSubmission;
use App\Models\Guru;
use App\Models\Lembaga;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

if (! function_exists('buatKasusDitugaskanKeGuruBk')) {
    function buatKasusDitugaskanKeGuruBk(Lembaga $lembaga): array
    {
        $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id]);

        $konselorUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
        Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'guru', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
        $role->givePermissionTo(['kasus.view']);
        $konselorUser->assignRole('guru');
        $guruBk = Guru::factory()->create([
            'user_id' => $konselorUser->id, 'lembaga_id' => $lembaga->id,
            'nik' => fake()->unique()->numerify('################'), 'nama' => 'Konselor BK',
            'jenis_kelamin' => 'P', 'jenis_ptk' => 'guru_bk', 'status_kepegawaian' => 'GTY',
            'status_aktif' => 'aktif',
        ]);

        $kasus = Kasus::create([
            'siswa_id' => $siswa->id, 'lembaga_id' => $lembaga->id,
            'kategori_masalah' => 'Perilaku', 'deskripsi' => 'Contoh.',
            'status' => StatusKasus::Ditugaskan, 'konselor_guru_id' => $guruBk->id,
        ]);

        return [$kasus, $konselorUser, $siswa];
    }
}

if (! function_exists('buatKasusDenganTugasDanKontakUtama')) {
    function buatKasusDenganTugasDanKontakUtama(Lembaga $lembaga): array
    {
        [$kasus, $konselorUser, $siswa] = buatKasusDitugaskanKeGuruBk($lembaga);

        Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);

        $siswaUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
        $siswaRole = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
        $siswaRole->givePermissionTo(['kasus.view']);
        $siswaUser->assignRole('siswa');
        $siswa->person->update(['user_id' => $siswaUser->id]);

        $orangTuaUser = User::factory()->create(['lembaga_id' => null]);
        $orangTuaRole = Role::firstOrCreate(['name' => 'orang_tua', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
        $orangTuaRole->givePermissionTo(['kasus.view']);
        $orangTuaUser->assignRole('orang_tua');
        $orangTua = OrangTua::factory()->create([
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
    Storage::fake('local');
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
    Storage::fake('local');
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
    Storage::disk('local')->assertExists($submission->lampiran);
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
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $unrelatedSiswaUser->id]);

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

it('rejects an empty submission (no teks, no lampiran) before media consent is approved', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [])
        ->assertSessionHasErrors('teks');

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->count())->toBe(0);
});

it('rejects an empty submission (no teks, no lampiran) after media consent is approved', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);
    KasusConsent::where('kasus_id', $kasus->id)->where('jenis', 'pengumpulan_media')
        ->update(['status' => 'disetujui', 'disetujui_at' => now()]);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), [])
        ->assertSessionHasErrors('teks');

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->count())->toBe(0);
});

it('403s a submission against a tugas already terlewat and creates no row', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);
    $tugas->update(['status' => 'terlewat']);

    $this->actingAs($siswaUser)->post(route('kasus.tugas.submission.store', [$kasus, $tugas]), ['teks' => 'x'])
        ->assertForbidden();

    expect(KasusTugasSubmission::where('tugas_id', $tugas->id)->count())->toBe(0);
});

it('renders a link to the lampiran in kasus.show for a konselor viewer', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas] = buatKasusDenganTugasDanKontakUtama($lembaga);
    $konselorUser = $kasus->konselorGuru->user;
    $submission = KasusTugasSubmission::factory()->create([
        'tugas_id' => $tugas->id,
        'teks' => 'Ada foto bukti.',
        'lampiran' => 'kasus-tugas-lampiran/bukti-test.jpg',
    ]);

    $this->actingAs($konselorUser)->get(route('kasus.show', $kasus))
        ->assertOk()
        ->assertSee(route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submission]), false)
        ->assertDontSee(asset('storage/kasus-tugas-lampiran/bukti-test.jpg'), false);
});

it('lets the assigned konselor download a submission lampiran', function () {
    Storage::fake('local');
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas] = buatKasusDenganTugasDanKontakUtama($lembaga);
    $konselorUser = $kasus->konselorGuru->user;

    Storage::disk('local')->put('kasus-tugas-lampiran/bukti-konselor.jpg', 'isi-file-bukti');
    $submission = KasusTugasSubmission::factory()->create([
        'tugas_id' => $tugas->id,
        'lampiran' => 'kasus-tugas-lampiran/bukti-konselor.jpg',
    ]);

    $response = $this->actingAs($konselorUser)
        ->get(route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submission]));

    $response->assertOk();
    expect($response->streamedContent())->toBe('isi-file-bukti');
});

it('lets the submitting siswa download their own submission lampiran', function () {
    Storage::fake('local');
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas, $siswaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);
    $siswa = $siswaUser->siswa;

    Storage::disk('local')->put('kasus-tugas-lampiran/bukti-siswa.jpg', 'isi-file-siswa');
    $submission = KasusTugasSubmission::factory()->create([
        'tugas_id' => $tugas->id,
        'siswa_id' => $siswa->id,
        'orang_tua_id' => null,
        'lampiran' => 'kasus-tugas-lampiran/bukti-siswa.jpg',
    ]);

    $response = $this->actingAs($siswaUser)
        ->get(route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submission]));

    $response->assertOk();
    expect($response->streamedContent())->toBe('isi-file-siswa');
});

it('404s an unrelated user attempting to download a submission lampiran', function () {
    Storage::fake('local');
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, $tugas] = buatKasusDenganTugasDanKontakUtama($lembaga);

    Storage::disk('local')->put('kasus-tugas-lampiran/bukti-rahasia.jpg', 'isi-rahasia');
    $submission = KasusTugasSubmission::factory()->create([
        'tugas_id' => $tugas->id,
        'lampiran' => 'kasus-tugas-lampiran/bukti-rahasia.jpg',
    ]);

    $unrelatedUser = User::factory()->create(['lembaga_id' => $lembaga->id]);
    Permission::firstOrCreate(['name' => 'kasus.view', 'guard_name' => 'web']);
    $siswaRole = Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);
    $siswaRole->givePermissionTo(['kasus.view']);
    $unrelatedUser->assignRole('siswa');
    Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'user_id' => $unrelatedUser->id]);

    $this->actingAs($unrelatedUser)
        ->get(route('kasus.tugas.submission.lampiran', [$kasus, $tugas, $submission]))
        ->assertNotFound();
});

it('shows the submission form to siswa/orang tua on kasus.show but not the konselor-only create form', function () {
    $lembaga = Lembaga::factory()->create(['yayasan_id' => Yayasan::factory()->create()->id]);
    [$kasus, , $siswaUser, $orangTuaUser] = buatKasusDenganTugasDanKontakUtama($lembaga);

    $this->actingAs($siswaUser)->get(route('kasus.show', $kasus))
        ->assertOk()
        ->assertSee('Kirim Bukti')
        ->assertDontSee('Beri Tugas');

    $this->actingAs($orangTuaUser)->get(route('kasus.show', $kasus))
        ->assertOk()
        ->assertSee('Kirim Bukti')
        ->assertDontSee('Beri Tugas');
});
