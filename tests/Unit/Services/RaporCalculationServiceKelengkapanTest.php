<?php

use App\Domains\Akademik\Enums\AssessmentType;
use App\Domains\Akademik\Models\Asesmen;
use App\Domains\Akademik\Models\KomponenPenilaian;
use App\Domains\Akademik\Models\MataPelajaran;
use App\Domains\Akademik\Models\NilaiSiswa;
use App\Domains\Akademik\Services\RaporCalculationService;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\Yayasan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function siapkanKelasUntukKelengkapan(): array
{
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $semester = Semester::factory()->create(['tahun_ajaran_id' => $tahunAjaran->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id]);
    $mapel = MataPelajaran::factory()->create(['lembaga_id' => $lembaga->id]);

    return compact('yayasan', 'lembaga', 'tahunAjaran', 'semester', 'kelas', 'mapel');
}

it('does not list a subjek at all when every siswa has filled every komponen', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'mapel' => $mapel] = siapkanKelasUntukKelengkapan();
    $siswaA = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $siswaB = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'assessment_type' => AssessmentType::Numeric]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaA->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 80]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaB->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 90]);

    $hasil = (new RaporCalculationService)->kelengkapanNilaiKelas($kelas, $semester);

    expect($hasil)->toBeEmpty();
});

it('lists only the siswa with a missing nilai_angka for a numeric komponen, not the ones already filled', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'mapel' => $mapel] = siapkanKelasUntukKelengkapan();
    $siswaLengkap = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $siswaBolong = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'assessment_type' => AssessmentType::Numeric]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaLengkap->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => 80]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswaBolong->id, 'komponen_penilaian_id' => $komponen->id, 'nilai_angka' => null]);

    $hasil = (new RaporCalculationService)->kelengkapanNilaiKelas($kelas, $semester);

    $sel = $hasil->get('mata_pelajaran:'.$mapel->id);
    expect($sel)->not->toBeNull();
    expect($sel->totalSiswa)->toBe(2);
    expect($sel->siswaBelumLengkap->pluck('id')->all())->toBe([$siswaBolong->id]);
});

it('treats a siswa as belum lengkap when the NilaiSiswa row does not exist at all for a komponen', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'mapel' => $mapel] = siapkanKelasUntukKelengkapan();
    $siswaTanpaNilai = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $komponen = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'assessment_type' => AssessmentType::Numeric]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach($komponen->id);
    // Sengaja TIDAK membuat baris NilaiSiswa sama sekali untuk siswa ini.

    $hasil = (new RaporCalculationService)->kelengkapanNilaiKelas($kelas, $semester);

    $sel = $hasil->get('mata_pelajaran:'.$mapel->id);
    expect($sel->siswaBelumLengkap->pluck('id')->all())->toBe([$siswaTanpaNilai->id]);
});

it('detects incompleteness for a predicate assessment type, not just numeric', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'mapel' => $mapel] = siapkanKelasUntukKelengkapan();
    $siswaPredikatKosong = Siswa::factory()->create(['kelas_id' => $kelas->id]);

    $komponenPredikat = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'assessment_type' => AssessmentType::Predicate]);
    $asesmenPredikat = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmenPredikat->komponenPenilaian()->attach($komponenPredikat->id);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmenPredikat->id, 'siswa_id' => $siswaPredikatKosong->id, 'komponen_penilaian_id' => $komponenPredikat->id, 'predikat' => null]);

    $hasil = (new RaporCalculationService)->kelengkapanNilaiKelas($kelas, $semester);

    expect($hasil->get('mata_pelajaran:'.$mapel->id)->siswaBelumLengkap->pluck('id')->all())->toBe([$siswaPredikatKosong->id]);
});

it('detects incompleteness for a narrative assessment type when catatan is blank/whitespace-only', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'mapel' => $mapel] = siapkanKelasUntukKelengkapan();
    $siswaNaratifKosong = Siswa::factory()->create(['kelas_id' => $kelas->id]);

    $komponenNaratif = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'assessment_type' => AssessmentType::Narrative]);
    $asesmenNaratif = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmenNaratif->komponenPenilaian()->attach($komponenNaratif->id);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmenNaratif->id, 'siswa_id' => $siswaNaratifKosong->id, 'komponen_penilaian_id' => $komponenNaratif->id, 'catatan' => '   ']);

    $hasil = (new RaporCalculationService)->kelengkapanNilaiKelas($kelas, $semester);

    expect($hasil->get('mata_pelajaran:'.$mapel->id)->siswaBelumLengkap->pluck('id')->all())->toBe([$siswaNaratifKosong->id]);
});

it('does not silently mark a siswa complete just because one of several komponen for the same subjek is filled', function () {
    ['kelas' => $kelas, 'semester' => $semester, 'mapel' => $mapel] = siapkanKelasUntukKelengkapan();
    $siswa = Siswa::factory()->create(['kelas_id' => $kelas->id]);
    $komponenSatu = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'assessment_type' => AssessmentType::Numeric, 'bobot' => 50]);
    $komponenDua = KomponenPenilaian::factory()->create(['subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id, 'assessment_type' => AssessmentType::Numeric, 'bobot' => 50]);
    $asesmen = Asesmen::factory()->create(['kelas_id' => $kelas->id, 'subjek_type' => 'mata_pelajaran', 'subjek_id' => $mapel->id, 'semester_id' => $semester->id]);
    $asesmen->komponenPenilaian()->attach([$komponenSatu->id, $komponenDua->id]);
    // hitungRekapKelas() akan tetap menghasilkan rata-rata (dari 1 nilai yang ada) untuk
    // siswa ini -- justru itu sebabnya method kelengkapan ini WAJIB independen, bukan
    // menyimpulkan dari null/tidaknya RekapNilaiSel.
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenSatu->id, 'nilai_angka' => 80]);
    NilaiSiswa::factory()->create(['asesmen_id' => $asesmen->id, 'siswa_id' => $siswa->id, 'komponen_penilaian_id' => $komponenDua->id, 'nilai_angka' => null]);

    $service = new RaporCalculationService;
    $rekap = $service->hitungRekapKelas($kelas, $semester)['rekapNilai'][$siswa->id]['mata_pelajaran:'.$mapel->id];
    expect($rekap)->not->toBeNull(); // pembuktian: rekap tetap "ada nilai" (rata-rata dari 1 nilai)

    $hasil = $service->kelengkapanNilaiKelas($kelas, $semester);
    expect($hasil->get('mata_pelajaran:'.$mapel->id)->siswaBelumLengkap->pluck('id')->all())->toBe([$siswa->id]);
});
