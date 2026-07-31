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
    Role::firstOrCreate(['name' => 'siswa', 'guard_name' => 'web'], ['scope_level' => 'diri_sendiri']);

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

it('denies template download without siswa.import permission', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.siswa.import.template'))->assertForbidden();
});

it('downloads a template with the exact header columns SiswaImportRow expects', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsSiswaImportManager($lembaga);

    $response = $this->actingAs($manager)->get(route('admin.siswa.import.template'));

    $response->assertOk();
    $response->assertHeader('content-disposition', 'attachment; filename=template-import-siswa.xlsx');

    $rows = \Maatwebsite\Excel\Facades\Excel::toArray(null, $response->getFile()->getPathname())[0];

    expect($rows[0])->toBe(['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas']);
    expect($rows)->toHaveCount(2);
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

it('creates a User account for every imported siswa', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'kode_lembaga' => 'SMPPRM']);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $manager = actingAsSiswaImportManager($lembaga);

    $file = buatFileImportSiswa([
        ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas'],
        ['3010', '0011111199', 'Siswa Import Akun', 'L', 'Bandung', '2014-01-01', 'Islam', '6A'],
    ]);

    $this->actingAs($manager)->post(route('admin.siswa.import.preview'), ['file' => $file]);
    $this->actingAs($manager)->post(route('admin.siswa.import.confirm'))->assertRedirect(route('admin.siswa.index'));

    $siswa = Siswa::where('nis', '3010')->firstOrFail();
    expect($siswa->user_id)->not->toBeNull();
    expect($siswa->user->username)->toBe($lembaga->kode_lembaga.'-'.$siswa->nis);
    expect(\Illuminate\Support\Facades\Hash::check($siswa->nis, $siswa->user->password))->toBeTrue();
});

it('flags a row whose NIS already exists in the database as invalid in the preview', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $manager = actingAsSiswaImportManager($lembaga);

    Siswa::factory()->create([
        'lembaga_id' => $lembaga->id,
        'kelas_id' => $kelas->id,
        'nis' => '3001',
        'nisn' => '0099999999',
    ]);

    $file = buatFileImportSiswa([
        ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas'],
        ['3001', '0011111111', 'Siswa NIS Bentrok', 'L', 'Bandung', '2014-01-01', 'Islam', '6A'],
    ]);

    $response = $this->actingAs($manager)->post(route('admin.siswa.import.preview'), ['file' => $file]);

    $response->assertOk();
    $response->assertViewHas('validRows', fn ($rows) => count($rows) === 0);
    $response->assertViewHas('invalidRows', function ($rows) {
        return count($rows) === 1
            && $rows[0]['nama_lengkap'] === 'Siswa NIS Bentrok'
            && str_contains($rows[0]['error'], 'NIS sudah dipakai');
    });

    expect(Siswa::where('nis', '3001')->count())->toBe(1);
});

it('does not classify both rows valid when two rows in the same file share a NIS', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $manager = actingAsSiswaImportManager($lembaga);

    $file = buatFileImportSiswa([
        ['nis', 'nisn', 'nama_lengkap', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama', 'kelas'],
        ['3005', '0055555551', 'Siswa Duplikat Pertama', 'L', 'Bandung', '2014-01-01', 'Islam', '6A'],
        ['3005', '0055555552', 'Siswa Duplikat Kedua', 'P', 'Bandung', '2014-02-02', 'Islam', '6A'],
    ]);

    $response = $this->actingAs($manager)->post(route('admin.siswa.import.preview'), ['file' => $file]);

    $response->assertOk();
    // Only the first occurrence of the duplicated NIS is kept valid; the
    // later row repeating it is flagged invalid instead — committing both
    // would still collide on (lembaga_id, nis).
    $response->assertViewHas('validRows', function ($rows) {
        return count($rows) === 1 && $rows[0]['nama_lengkap'] === 'Siswa Duplikat Pertama';
    });
    $response->assertViewHas('invalidRows', function ($rows) {
        return count($rows) === 1
            && $rows[0]['nama_lengkap'] === 'Siswa Duplikat Kedua'
            && str_contains($rows[0]['error'], 'duplikat');
    });
});

it('rolls back the whole batch and creates zero siswa when a row in the valid session set collides mid-loop', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $tahunAjaran = TahunAjaran::factory()->create(['lembaga_id' => $lembaga->id]);
    $kelas = Kelas::factory()->create(['lembaga_id' => $lembaga->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'nama' => '6A']);
    $manager = actingAsSiswaImportManager($lembaga);

    // Bypass parseAll()'s in-file duplicate detection entirely by writing
    // two rows with the same NIS straight into the session, simulating a
    // batch that "somehow" slipped past validation. This isolates the
    // confirm() transaction-rollback behaviour from the preview validation
    // logic covered by the tests above.
    session(['siswa_import_valid_rows' => [
        [
            'nis' => '3009', 'nisn' => '0099999901', 'nama_lengkap' => 'Siswa Rollback Satu',
            'jenis_kelamin' => 'L', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2014-01-01',
            'agama' => 'Islam', 'kelas_nama' => '6A', 'kelas_id' => $kelas->id,
        ],
        [
            'nis' => '3009', 'nisn' => '0099999902', 'nama_lengkap' => 'Siswa Rollback Dua',
            'jenis_kelamin' => 'P', 'tempat_lahir' => 'Bandung', 'tanggal_lahir' => '2014-02-02',
            'agama' => 'Islam', 'kelas_nama' => '6A', 'kelas_id' => $kelas->id,
        ],
    ]]);

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs($manager)->post(route('admin.siswa.import.confirm')))
        ->toThrow(\Illuminate\Database\QueryException::class);

    expect(Siswa::where('lembaga_id', $lembaga->id)->count())->toBe(0);
});
