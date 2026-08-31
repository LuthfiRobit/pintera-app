<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Identity\Actions\UpdatePersonAction;
use App\Domains\Identity\Models\Person;
use App\Domains\Sdm\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use App\Models\Yayasan;
use App\Services\AkunKaryawanGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class KaryawanController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('karyawan.view');

        $search = $request->query('search');
        $user = $request->user();
        $lembagaId = $this->resolveLembagaId($request);

        // TenantScope only ever emits "WHERE lembaga_id = <viewer's active lembaga>", which
        // excludes pool karyawan (lembaga_id IS NULL) entirely for any viewer with an active
        // lembaga context. Pool karyawan belong to a yayasan, not a lembaga, so they need to
        // be surfaced alongside the viewer's own dedicated karyawan whenever a lembaga context
        // is active. We bypass the global scope here and rebuild the equivalent filter by hand
        // (dedicated karyawan for this lembaga OR pool karyawan for this lembaga's yayasan).
        $karyawanList = Karyawan::withoutGlobalScope(TenantScope::class)
            ->with(['user', 'jenisKaryawan', 'lembaga', 'person'])
            ->when(
                $lembagaId !== null,
                function ($query) use ($lembagaId) {
                    $yayasanId = Lembaga::find($lembagaId)?->yayasan_id;

                    $query->where(function ($q) use ($lembagaId, $yayasanId) {
                        $q->where('karyawan.lembaga_id', $lembagaId)
                            ->orWhere(function ($q2) use ($yayasanId) {
                                $q2->whereNull('karyawan.lembaga_id')->where('karyawan.yayasan_id', $yayasanId);
                            });
                    });
                },
                function ($query) use ($user, $lembagaId) {
                    // No specific lembaga context resolved. For a non-yayasan-scoped viewer
                    // this preserves TenantScope's prior (edge-case) behavior; a yayasan-scoped
                    // viewer with no active lembaga ("all lembaga" mode) sees everything
                    // unfiltered, matching the pre-existing behavior of that mode.
                    if ($user->widestScopeLevel() !== 'yayasan') {
                        $query->where('karyawan.lembaga_id', $lembagaId);
                    }
                }
            )
            ->when($search, fn ($q) => $q->search($search))
            ->orderByNama()
            ->get();

        $totalKaryawan = $karyawanList->count();
        $totalAktif = $karyawanList->where('status_aktif', 'aktif')->count();
        $totalPool = $karyawanList->whereNull('lembaga_id')->count();

        return view('admin.karyawan.index', [
            'karyawanList' => $karyawanList,
            'search' => $search,
            'totalKaryawan' => $totalKaryawan,
            'totalAktif' => $totalAktif,
            'totalPool' => $totalPool,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('karyawan.create');

        return view('admin.karyawan.create', [
            'jenisKaryawanList' => JenisKaryawanMaster::orderBy('nama')->get(),
            'yayasanList' => $request->user()->hasRole('yayasan_super_admin') ? Yayasan::orderBy('nama')->get() : collect(),
            'canCreatePool' => $request->user()->hasRole('yayasan_super_admin'),
        ]);
    }

    public function store(Request $request, AkunKaryawanGenerator $generator): RedirectResponse
    {
        $this->authorize('karyawan.create');

        $isPool = $request->boolean('is_pool');
        if ($isPool && ! $request->user()->hasRole('yayasan_super_admin')) {
            abort(403, 'Hanya yayasan super admin yang bisa membuat karyawan pool.');
        }

        $data = $this->validateProfil($request);

        // withoutGlobalScopes(): User also uses BelongsToTenant, so a plain query would be
        // silently filtered to the acting admin's own lembaga_id and miss exactly the
        // cross-lembaga/cross-tenant collisions (e.g. an existing guru or orang_tua account
        // in a different lembaga) this check exists to catch.
        if (User::withoutGlobalScopes()->where('username', $data['nik'])->exists()) {
            return back()
                ->withErrors(['nik' => 'NIK ini sudah terdaftar untuk akun lain.'])
                ->withInput();
        }

        if ($isPool) {
            $yayasanData = $request->validate(['yayasan_id' => ['required', 'exists:yayasan,id']]);
            $yayasanId = (int) $yayasanData['yayasan_id'];
            $lembagaId = null;
        } else {
            $lembagaId = $this->resolveLembagaId($request);
            if ($lembagaId === null) {
                return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah data karyawan.'])->withInput();
            }
            $yayasanId = Lembaga::findOrFail($lembagaId)->yayasan_id;
        }

        $generator->buat(
            $data['nama'],
            $data['nik'],
            $yayasanId,
            $lembagaId,
            (int) $data['jenis_karyawan_id'],
            $data['no_hp'] ?? null,
            $data['email'] ?? null,
        );

        return redirect()->route('admin.karyawan.index')->with('status', 'Data karyawan & akun berhasil dibuat.');
    }

    public function edit(Karyawan $karyawan): View
    {
        $this->authorize('karyawan.edit');

        $karyawan->load([
            'user' => fn ($q) => $q->withoutGlobalScope(TenantScope::class),
            'person',
            'jenisKaryawan',
            'lembaga',
            'yayasan',
        ]);

        return view('admin.karyawan.edit', [
            'karyawan' => $karyawan,
            'jenisKaryawanList' => JenisKaryawanMaster::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $this->authorize('karyawan.edit');

        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'jenis_karyawan_id' => ['required', 'exists:jenis_karyawan_master,id'],
        ]);

        DB::transaction(function () use ($data, $karyawan) {
            if ($karyawan->person) {
                app(UpdatePersonAction::class)->execute($karyawan->person, [
                    'nama_lengkap' => $data['nama'],
                    'no_hp' => $data['no_hp'] ?? $karyawan->person->no_hp,
                    'email' => $data['email'] ?? $karyawan->person->email,
                ]);

                if ($karyawan->person->user !== null) {
                    $karyawan->person->user->update(['name' => $data['nama']]);
                }
            } elseif ($karyawan->user !== null) {
                $karyawan->user()->update(['name' => $data['nama']]);
            }

            $karyawan->update([
                'jenis_karyawan_id' => $data['jenis_karyawan_id'],
            ]);
        });

        return redirect()->route('admin.karyawan.index')->with('status', 'Data karyawan berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $this->authorize('karyawan.edit');

        $data = $request->validate([
            'status_aktif' => ['required', 'in:aktif,non_aktif'],
        ]);

        DB::transaction(function () use ($data, $karyawan) {
            $karyawan->update(['status_aktif' => $data['status_aktif']]);
            $karyawan->user()->update(['is_active' => $data['status_aktif'] === 'aktif']);
        });

        return redirect()->route('admin.karyawan.index')->with('status', 'Status karyawan berhasil diperbarui.');
    }

    private function resolveLembagaId(Request $request): ?int
    {
        if ($request->user()->widestScopeLevel() === 'yayasan') {
            return session('active_lembaga_id');
        }

        return $request->user()->lembaga_id;
    }

    private function validateProfil(Request $request, ?Karyawan $karyawan = null): array
    {
        $isPool = $request->boolean('is_pool');
        $yayasanId = null;
        if ($isPool) {
            $yayasanId = (int) $request->input('yayasan_id');
        } else {
            $lembagaId = $this->resolveLembagaId($request);
            $yayasanId = $lembagaId ? Lembaga::withoutGlobalScopes()->find($lembagaId)?->yayasan_id : ($request->user()?->yayasan_id ?? $request->user()?->lembaga?->yayasan_id);
        }

        return $request->validate([
            'nik' => ['required', 'digits:16', function ($attribute, $value, $fail) use ($karyawan, $yayasanId) {
                $query = Person::withoutGlobalScopes()->where('nik_hash', hash('sha256', $value));
                if ($yayasanId) {
                    $query->where('yayasan_id', $yayasanId);
                }
                if ($karyawan && $karyawan->person_id) {
                    $query->where('id', '!=', $karyawan->person_id);
                }
                if ($query->exists()) {
                    $fail('NIK sudah terdaftar untuk karyawan lain.');
                }
            }],
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'jenis_karyawan_id' => ['required', 'exists:jenis_karyawan_master,id'],
        ]);
    }
}
