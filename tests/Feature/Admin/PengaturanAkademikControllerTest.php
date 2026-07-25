<?php

use App\Http\Controllers\Admin\PengaturanAkademikController;
use App\Models\KalenderAkademik;
use App\Models\Lembaga;
use App\Models\Role;
use App\Models\User;
use App\Models\Yayasan;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

function actingAsPengaturanAkademikManager(Lembaga $lembaga, array $permissions = ['kalender-akademik.view', 'pengaturan-akademik.kelola']): User
{
    foreach ($permissions as $permission) {
        Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }
    $role = Role::firstOrCreate(['name' => 'admin_pengaturan_akademik_'.$lembaga->id, 'guard_name' => 'web'], ['scope_level' => 'lembaga']);
    $role->syncPermissions($permissions);

    $manager = User::factory()->create(['lembaga_id' => $lembaga->id]);
    $manager->assignRole($role);

    return $manager;
}

it('denies access to a user without kalender-akademik.view', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $user = User::factory()->create(['lembaga_id' => $lembaga->id]);

    $this->actingAs($user)
        ->get(route('admin.pengaturan.akademik.index'))
        ->assertForbidden();
});

it('shows the acting lembaga-scoped user\'s own hari_libur_mingguan and kalender entries', function () {
    // The admin.pengaturan.akademik blade file is built by Task 5 and
    // doesn't exist yet. Laravel resolves the view file (via the view
    // finder) inside Factory::make() itself -- i.e. as soon as the
    // controller calls view(), independent of rendering and independent
    // of which TestResponse assertion runs afterwards -- so a real HTTP
    // round trip 500s regardless of assertOk()/assertViewIs(). To exercise
    // the real controller code without creating a stub blade file, the
    // 'view' container binding is swapped for a fake factory whose make()
    // constructs a real Illuminate\View\View (satisfying the controller's
    // return type) without touching the filesystem.
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0, 6]]);
    $lembagaLain = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPengaturanAkademikManager($lembaga, ['kalender-akademik.view']);

    $entriNasional = KalenderAkademik::create(['lembaga_id' => null, 'tanggal' => '2026-08-17', 'nama' => 'Hari Kemerdekaan RI', 'tipe' => 'libur']);
    $entriMilikSendiri = KalenderAkademik::create(['lembaga_id' => $lembaga->id, 'tanggal' => '2026-09-01', 'nama' => 'Libur Lembaga Sendiri', 'tipe' => 'libur']);
    $entriLembagaLain = KalenderAkademik::create(['lembaga_id' => $lembagaLain->id, 'tanggal' => '2026-09-05', 'nama' => 'Libur Lembaga Lain', 'tipe' => 'libur']);

    $this->actingAs($manager);

    $realFactory = app('view');
    $engine = app('view.engine.resolver')->resolve('blade');
    app()->instance('view', new class($realFactory, $engine) implements \Illuminate\Contracts\View\Factory
    {
        public function __construct(private $factory, private $engine) {}

        public function exists($view)
        {
            return true;
        }

        public function file($path, $data = [], $mergeData = [])
        {
            return $this->factory->file($path, $data, $mergeData);
        }

        public function make($view, $data = [], $mergeData = [])
        {
            return new \Illuminate\View\View($this->factory, $this->engine, $view, 'stub-path', array_merge((array) $data, (array) $mergeData));
        }

        public function share($key, $value = null)
        {
            return $this->factory->share($key, $value);
        }

        public function composer($views, $callback)
        {
            return $this->factory->composer($views, $callback);
        }

        public function creator($views, $callback)
        {
            return $this->factory->creator($views, $callback);
        }

        public function addNamespace($namespace, $hints)
        {
            return $this;
        }

        public function replaceNamespace($namespace, $hints)
        {
            return $this;
        }
    });

    $request = Request::create(route('admin.pengaturan.akademik.index'), 'GET');
    $request->setUserResolver(fn () => $manager);

    $view = app(PengaturanAkademikController::class)->index($request);

    expect($view->name())->toBe('admin.pengaturan.akademik');

    $viewLembaga = $view->getData()['lembaga'];
    expect($viewLembaga->id)->toBe($lembaga->id);
    expect($viewLembaga->hari_libur_mingguan)->toBe([0, 6]);

    $entriList = $view->getData()['entriList'];
    $ids = $entriList->pluck('id')->all();
    expect($ids)->toContain($entriNasional->id);
    expect($ids)->toContain($entriMilikSendiri->id);
    expect($ids)->not->toContain($entriLembagaLain->id);
});

it('renders the pengaturan akademik page for an authorized user', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPengaturanAkademikManager($lembaga, ['kalender-akademik.view']);

    $this->actingAs($manager)
        ->get(route('admin.pengaturan.akademik.index'))
        ->assertOk()
        ->assertViewIs('admin.pengaturan.akademik');
});

it('denies saving hari_libur_mingguan without pengaturan-akademik.kelola', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id]);
    $manager = actingAsPengaturanAkademikManager($lembaga, ['kalender-akademik.view']);

    $this->actingAs($manager)
        ->putJson(route('admin.pengaturan.akademik.hari-aktif'), ['hari_aktif' => [1, 2, 3, 4, 5]])
        ->assertForbidden();
});

it('saves a new hari_libur_mingguan for the acting lembaga-scoped user\'s own lembaga', function () {
    $yayasan = Yayasan::factory()->create();
    $lembaga = Lembaga::factory()->create(['yayasan_id' => $yayasan->id, 'hari_libur_mingguan' => [0]]);
    $manager = actingAsPengaturanAkademikManager($lembaga, ['pengaturan-akademik.kelola']);

    $this->actingAs($manager)
        ->putJson(route('admin.pengaturan.akademik.hari-aktif'), ['hari_aktif' => [1, 2, 3, 4, 6]])
        ->assertOk();

    expect($lembaga->fresh()->hari_libur_mingguan)->toBe([0, 5]);
});

it('does not let a yayasan-scoped user without an active lembaga save hari_libur_mingguan', function () {
    Permission::firstOrCreate(['name' => 'pengaturan-akademik.kelola', 'guard_name' => 'web']);
    $role = Role::firstOrCreate(['name' => 'yayasan_pengaturan_akademik', 'guard_name' => 'web'], ['scope_level' => 'yayasan']);
    $role->syncPermissions(['pengaturan-akademik.kelola']);

    $manager = User::factory()->create(['lembaga_id' => null]);
    $manager->assignRole($role);

    $this->actingAs($manager)
        ->putJson(route('admin.pengaturan.akademik.hari-aktif'), ['hari_aktif' => [1, 2, 3, 4, 5]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('lembaga_id');
});
