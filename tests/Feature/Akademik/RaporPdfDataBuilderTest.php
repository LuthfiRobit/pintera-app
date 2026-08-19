<?php

use App\Domains\Akademik\Services\RaporPdfDataBuilder;

it('maps bentuk_pendidikan to the correct template, defaulting unknown values to sd', function () {
    $builder = app(RaporPdfDataBuilder::class);

    expect($builder->templateUntukJenjang('KB'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('TPA'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('SPS'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('TK'))->toBe('pdf.rapor.paud');
    expect($builder->templateUntukJenjang('SMK'))->toBe('pdf.rapor.smk');
    expect($builder->templateUntukJenjang('SMP'))->toBe('pdf.rapor.smp-sma');
    expect($builder->templateUntukJenjang('SMA'))->toBe('pdf.rapor.smp-sma');
    expect($builder->templateUntukJenjang('SD'))->toBe('pdf.rapor.sd');
    expect($builder->templateUntukJenjang('SLB'))->toBe('pdf.rapor.sd');
    expect($builder->templateUntukJenjang('NILAI_TAK_DIKENAL'))->toBe('pdf.rapor.sd');
});

use App\Domains\Akademik\Actions\Rapor\SimpanCatatanWaliKelasAction;
use App\Domains\Akademik\Actions\Rapor\SubmitPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\VerifyPengajuanRaporAction;
use App\Domains\Akademik\Actions\Rapor\ApprovePengajuanRaporAction;
use App\Domains\Akademik\DataTransferObjects\CatatanWaliKelasData;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Workflow\Actions\InitializeApprovalRequestAction;
use App\Domains\Workflow\Actions\ProcessApprovalAction;
use App\Domains\Workflow\Enums\ApprovalAction;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\MataPelajaran;
use App\Models\OrangTua;
use App\Models\Role;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Database\Seeders\WorkflowDefinitionSeeder;

function siapkanSiswaLengkapUntukPdf(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'bentuk_pendidikan' => 'SD']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id, 'urutan' => 1, 'nama' => 'Ganjil']);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $siswa = Siswa::factory()->create(['lembaga_id' => $lembaga->id, 'kelas_id' => $kelas->id, 'status' => 'aktif']);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id]);
    $komponen = KomponenPenilaian::factory()->create(['mata_pelajaran_id' => $mapel->id, 'semester_id' => $semester->id, 'kktp_minimal' => 75]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]);

    $orangTua = OrangTua::factory()->create(['nama_lengkap' => 'Budi Orang Tua']);
    $siswa->orangTua()->attach($orangTua->id, ['hubungan' => 'ayah', 'is_kontak_utama' => true]);

    return compact('yayasan', 'lembaga', 'tahunAjaran', 'semester', 'kelas', 'siswa', 'mapel');
}

it('builds a complete data array for a siswa with nilai, catatan, and approval', function () {
    $this->seed(WorkflowDefinitionSeeder::class);
    ['kelas' => $kelas, 'siswa' => $siswa, 'semester' => $semester] = siapkanSiswaLengkapUntukPdf();

    $roleWaka = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web']);
    $userWaka = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $userWaka->assignRole($roleWaka);
    $roleKepsek = Role::firstOrCreate(['name' => 'kepala_sekolah', 'guard_name' => 'web']);
    $userKepsek = User::factory()->create(['lembaga_id' => $kelas->lembaga_id]);
    $userKepsek->assignRole($roleKepsek);
    \App\Models\Guru::factory()->create(['user_id' => $userWaka->id, 'lembaga_id' => $kelas->lembaga_id, 'nama' => 'Bu Waka']);
    \App\Models\Guru::factory()->create(['user_id' => $userKepsek->id, 'lembaga_id' => $kelas->lembaga_id, 'nama' => 'Pak Kepsek']);

    (new SimpanCatatanWaliKelasAction())->execute(CatatanWaliKelasData::fromArray(['siswa_id' => $siswa->id, 'semester_id' => $semester->id, 'catatan_sikap' => 'Baik']));
    $pengajuan = (new SubmitPengajuanRaporAction(app(InitializeApprovalRequestAction::class)))->execute($kelas, $semester, $userWaka);
    (new VerifyPengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan, $userWaka, ApprovalAction::Approve);
    (new ApprovePengajuanRaporAction(app(ProcessApprovalAction::class)))->execute($pengajuan->fresh(), $userKepsek, ApprovalAction::Approve);

    $data = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswa->fresh(), $semester);

    expect($data['siswa']->id)->toBe($siswa->id);
    expect($data['catatan']->catatan_sikap)->toBe('Baik');
    expect($data['isDraft'])->toBeFalse();
    expect($data['namaWaliKelas'])->toBe('Bu Waka');
    expect($data['namaKepalaSekolah'])->toBe('Pak Kepsek');
    expect($data['namaOrangTua'])->toBe('Budi Orang Tua');
});

it('marks isDraft true and leaves signature names null when nothing has been submitted yet', function () {
    ['siswa' => $siswa, 'semester' => $semester] = siapkanSiswaLengkapUntukPdf();

    $data = app(\App\Domains\Akademik\Services\RaporPdfDataBuilder::class)->build($siswa, $semester);

    expect($data['isDraft'])->toBeTrue();
    expect($data['namaWaliKelas'])->toBeNull();
    expect($data['namaKepalaSekolah'])->toBeNull();
    expect($data['pengajuanRapor'])->toBeNull();
});
