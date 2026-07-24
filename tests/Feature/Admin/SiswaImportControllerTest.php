<?php

use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

function actingAsSiswaImportManager(Lembaga $lembaga): User
{
    Permission::firstOrCreate(['name' => 'siswa.import', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'admin_akademik', 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->givePermissionTo(['siswa.import']);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

class SiswaImportFixtureExport implements \Maatwebsite\Excel\Concerns\FromArray
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }
}

function buatFileImportSiswa(array $rows): UploadedFile
{
    $filename = 'test-import-siswa-'.uniqid().'.xlsx';

    Excel::store(new SiswaImportFixtureExport($rows), $filename, 'local');

    $absolutePath = Storage::disk('local')->path($filename);

    return new UploadedFile($absolutePath, 'siswa.xlsx', null, null, true);
}

it('denies access without siswa.import permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.siswa.import.index'))->assertForbidden();
});

it('splits uploaded rows into valid and invalid in the preview, matching kelas by name', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $manager = actingAsSiswaImportManager($lembaga);

    $file = buatFileImportSiswa([
        ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas'],
        ['3001', '0011111111', 'Siswa Valid', 'L', 'Bandung', '2014-01-01', 'Islam', '6A'],
        ['3002', '0022222222', 'Siswa Kelas Tak Ditemukan', 'P', 'Bandung', '2014-02-02', 'Islam', 'Kelas Tidak Ada'],
    ]);

    $response = $this->actingAs($manager)->post(route('admin.siswa.import.preview'), ['file' => $file]);

    $response->assertOk();
    $response->assertViewHas('validRows', fn ($rows) => count($rows) === 1 && $rows[0]['nama_lengkap'] === 'Siswa Valid');
    $response->assertViewHas('invalidRows', fn ($rows) => count($rows) === 1 && $rows[0]['nama_lengkap'] === 'Siswa Kelas Tak Ditemukan');
});

it('commits only the valid rows held in session when confirmed', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $manager = actingAsSiswaImportManager($lembaga);

    $file = buatFileImportSiswa([
        ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas'],
        ['3003', '0033333333', 'Siswa Import Sukses', 'L', 'Bandung', '2014-01-01', 'Islam', '6A'],
    ]);

    $this->actingAs($manager)->post(route('admin.siswa.import.preview'), ['file' => $file]);
    $this->actingAs($manager)->post(route('admin.siswa.import.confirm'))->assertRedirect(route('admin.siswa.index'));

    $siswa = Siswa::where('nis', '3003')->firstOrFail();
    expect($siswa->sumber_data)->toBe(SumberDataSiswa::Import);
    expect($siswa->kelas_id)->toBe($kelas->id);
});
