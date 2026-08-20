<?php

use App\Services\PermissionUsageScanner;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

uses(TestCase::class);

function buatFileSementara(string $relatifDir, string $namaFile, string $isi): string
{
    $dir = base_path($relatifDir);
    File::ensureDirectoryExists($dir);
    $path = $dir.DIRECTORY_SEPARATOR.$namaFile;
    File::put($path, $isi);

    return $path;
}

afterEach(function () {
    File::deleteDirectory(base_path('tests/tmp-scanner-fixture'));
});

it('extracts a 2-segment and a 3-segment authorize() permission from a controller-like file', function () {
    buatFileSementara('tests/tmp-scanner-fixture/controllers', 'Fixture1Controller.php', <<<'PHP'
<?php
class Fixture1Controller {
    public function index() {
        $this->authorize('rapor.verify');
    }
    public function submit() {
        $this->authorize('pengadaan.lpj.submit');
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/controllers']);

    expect(array_keys($found))->toContain('rapor.verify', 'pengadaan.lpj.submit');
});

it('extracts every permission listed inside a canAny([...]) call', function () {
    buatFileSementara('tests/tmp-scanner-fixture/controllers', 'Fixture2Controller.php', <<<'PHP'
<?php
class Fixture2Controller {
    public function index() {
        abort_unless($this->request->user()->canAny(['rapor.verify', 'rapor.approve']), 403);
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/controllers']);

    expect(array_keys($found))->toContain('rapor.verify', 'rapor.approve');
});

it('extracts a can() permission from a FormRequest-like authorize() method', function () {
    buatFileSementara('tests/tmp-scanner-fixture/requests', 'FixtureRequest.php', <<<'PHP'
<?php
class FixtureRequest {
    public function authorize(): bool {
        return $this->user()->can('rapor.ajukan');
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/requests']);

    expect(array_keys($found))->toContain('rapor.ajukan');
});

it('extracts @can and @canany permission names from a blade-like file', function () {
    buatFileSementara('tests/tmp-scanner-fixture/views', 'fixture.blade.php', <<<'PHP'
@can('rapor.verify')
    <p>Visible</p>
@endcan
@canany(['rapor.verify', 'rapor.approve'])
    <p>Also visible</p>
@endcanany
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/views']);

    expect(array_keys($found))->toContain('rapor.verify', 'rapor.approve');
});

it('records which file each permission was found in', function () {
    $path = buatFileSementara('tests/tmp-scanner-fixture/controllers', 'Fixture3Controller.php', <<<'PHP'
<?php
class Fixture3Controller {
    public function index() {
        $this->authorize('kasus.view');
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanCodeUsage(['tests/tmp-scanner-fixture/controllers']);

    expect($found['kasus.view'])->toContain($path);
});

it('extracts registered permission names from a seeder-like file, ignoring the array variable indirection', function () {
    buatFileSementara('tests/tmp-scanner-fixture/seeders', 'FixtureSeeder.php', <<<'PHP'
<?php
class FixtureSeeder {
    public function run(): void {
        $permissions = ['rapor.verify', 'pengadaan.lpj.submit'];
        foreach ($permissions as $name) {
            \Spatie\Permission\Models\Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
PHP);

    $found = (new PermissionUsageScanner())->scanSeederRegistrations(['tests/tmp-scanner-fixture/seeders/FixtureSeeder.php']);

    expect(array_keys($found))->toContain('rapor.verify', 'pengadaan.lpj.submit');
});
