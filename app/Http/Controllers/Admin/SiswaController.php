<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Identity\Actions\CreatePersonAction;
use App\Domains\Identity\Actions\UpdatePersonAction;
use App\Enums\StatusSiswa;
use App\Enums\SumberDataSiswa;
use App\Models\Kelas;
use App\Models\Lembaga;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Services\AkunSiswaGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SiswaController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('siswa.view');

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50]) ? (int) $request->input('per_page') : 20;

        $query = Siswa::with(['kelas', 'person'])
            ->when($request->input('search'), fn ($q, $search) => $q->search($search))
            ->when($request->input('kelas_id'), fn ($q, $kelasId) => $q->where('siswa.kelas_id', $kelasId))
            ->when($request->input('status'), fn ($q, $status) => $q->where('siswa.status', $status))
            ->orderByNama();

        // Build kelas list from the acting lembaga's active tahun ajaran only.
        // Left empty for a yayasan-scoped user with no active lembaga selected —
        // there is no single "the" active tahun ajaran to pick in that state.
        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        $tahunAjaranAktif = $lembagaId ? TahunAjaran::where('status_aktif', true)->first() : null;
        $kelasList = $tahunAjaranAktif
            ? Kelas::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
            : collect();

        $siswaTanpaAkunCount = Siswa::where('status', StatusSiswa::Aktif->value)->whereNull('user_id')->count();
        $totalSiswa = Siswa::count();
        $totalAktif = Siswa::where('status', StatusSiswa::Aktif->value)->count();

        $paginated = $query->paginate($perPage)->withQueryString();

        if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            return view('admin.siswa._daftar', [
                'siswaList' => $paginated,
                'perPage' => $perPage,
            ]);
        }

        return view('admin.siswa.index', [
            'siswaList' => $paginated,
            'kelasList' => $kelasList,
            'statusList' => StatusSiswa::cases(),
            'perPage' => $perPage,
            'totalSiswa' => $totalSiswa,
            'totalAktif' => $totalAktif,
            'siswaTanpaAkunCount' => $siswaTanpaAkunCount,
        ]);
    }

    public function create(): View
    {
        $this->authorize('siswa.create');

        return view('admin.siswa.create', [
            'kelasList' => Kelas::orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('siswa.create');

        $data = $this->validateSiswa($request);
        $data['sumber_data'] = SumberDataSiswa::Manual->value;

        $lembagaId = $request->user()->widestScopeLevel() === 'yayasan'
            ? session('active_lembaga_id')
            : $request->user()->lembaga_id;

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif melalui pengalih lembaga sebelum menambah siswa.'])->withInput();
        }

        $data['lembaga_id'] = $lembagaId;

        DB::transaction(function () use ($data, $lembagaId) {
            $lembaga = Lembaga::withoutGlobalScopes()->findOrFail($lembagaId);

            $person = app(CreatePersonAction::class)->execute(
                identityData: [
                    'nama_lengkap' => $data['nama_lengkap'],
                    'jenis_kelamin' => $data['jenis_kelamin'] ?? null,
                    'tempat_lahir' => $data['tempat_lahir'] ?? null,
                    'tanggal_lahir' => $data['tanggal_lahir'] ?? null,
                    'agama' => $data['agama'] ?? null,
                ],
                lembagaId: $lembagaId,
                actingYayasanId: null,
            );

            $user = app(AkunSiswaGenerator::class)->buat($data['nama_lengkap'], $data['nis'], $lembaga);
            $person->update(['user_id' => $user->id]);

            Siswa::create([
                'person_id' => $person->id,
                'user_id' => $user->id,
                'lembaga_id' => $lembagaId,
                'kelas_id' => $data['kelas_id'] ?? null,
                'nis' => $data['nis'],
                'nisn' => $data['nisn'] ?? null,
                'status' => StatusSiswa::Aktif->value,
                'sumber_data' => $data['sumber_data'] ?? SumberDataSiswa::Manual->value,
            ]);
        });

        return redirect()->route('admin.siswa.index')->with('status', 'Siswa & akun berhasil disimpan.');
    }

    public function edit(Siswa $siswa): View
    {
        $this->authorize('siswa.edit');

        $siswa->load(['orangTua', 'person']);

        return view('admin.siswa.edit', [
            'siswa' => $siswa,
            'kelasList' => Kelas::orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $data = $this->validateSiswa($request, $siswa);

        DB::transaction(function () use ($data, $siswa) {
            if ($siswa->person) {
                app(UpdatePersonAction::class)->execute($siswa->person, [
                    'nama_lengkap' => $data['nama_lengkap'],
                    'jenis_kelamin' => $data['jenis_kelamin'] ?? $siswa->person->jenis_kelamin,
                    'tempat_lahir' => $data['tempat_lahir'] ?? $siswa->person->tempat_lahir,
                    'tanggal_lahir' => $data['tanggal_lahir'] ?? $siswa->person->tanggal_lahir,
                    'agama' => $data['agama'] ?? $siswa->person->agama,
                ]);

                $user = $siswa->person->user ?? ($siswa->person->user_id ? User::withoutGlobalScopes()->find($siswa->person->user_id) : null);
                if ($user !== null) {
                    $user->update([
                        'name' => $data['nama_lengkap'],
                        'username' => app(AkunSiswaGenerator::class)->usernameUntuk($siswa->lembaga, $data['nis']),
                    ]);
                }
            } elseif ($siswa->user_id) {
                $user = User::withoutGlobalScopes()->find($siswa->user_id);
                if ($user !== null) {
                    $user->update([
                        'name' => $data['nama_lengkap'],
                        'username' => app(AkunSiswaGenerator::class)->usernameUntuk($siswa->lembaga, $data['nis']),
                    ]);
                }
            }

            $siswa->update(collect($data)->only([
                'kelas_id', 'nis', 'nisn',
            ])->toArray());
        });

        return redirect()->route('admin.siswa.index')->with('status', 'Siswa berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $data = $request->validate([
            'status' => ['required', 'in:aktif,lulus,pindah,keluar'],
        ]);

        DB::transaction(function () use ($data, $siswa) {
            $siswa->update(['status' => $data['status']]);

            if ($siswa->user_id) {
                $siswa->user()->update(['is_active' => $data['status'] === StatusSiswa::Aktif->value]);
            }
        });

        return redirect()->route('admin.siswa.index')->with('status', 'Status siswa berhasil diperbarui.');
    }

    public function resetPassword(Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa.edit');

        if (! $siswa->user_id) {
            return back()->withErrors(['user' => 'Siswa ini belum punya akun login.']);
        }

        $siswa->user->update([
            'password' => Hash::make($siswa->nis),
            'must_change_password' => true,
        ]);

        Log::info('Admin reset siswa password', [
            'admin_user_id' => auth()->id(),
            'siswa_id' => $siswa->id,
            'target_user_id' => $siswa->user->id,
        ]);

        return redirect()->route('admin.siswa.index')->with('status', 'Password siswa berhasil direset ke NIS. Siswa wajib mengganti password saat login berikutnya.');
    }

    public function generateAkunMassal(Request $request): RedirectResponse
    {
        $this->authorize('siswa.edit');

        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');

        if ($lembagaId === null) {
            return back()->withErrors(['lembaga_id' => 'Pilih lembaga aktif terlebih dahulu.']);
        }

        $lembaga = Lembaga::withoutGlobalScopes()->findOrFail($lembagaId);
        $siswaWithoutAccount = Siswa::where('status', StatusSiswa::Aktif->value)
            ->whereNull('user_id')
            ->get();

        if ($siswaWithoutAccount->isEmpty()) {
            return back()->with('status', 'Semua siswa aktif sudah memiliki akun login.');
        }

        DB::transaction(function () use ($siswaWithoutAccount, $lembaga) {
            $generator = app(AkunSiswaGenerator::class);
            foreach ($siswaWithoutAccount as $siswa) {
                $user = $generator->buat($siswa->nama_lengkap, $siswa->nis, $lembaga);
                if ($siswa->person) {
                    $siswa->person->update(['user_id' => $user->id]);
                }
                $siswa->update(['user_id' => $user->id]);
            }
        });

        return back()->with('status', count($siswaWithoutAccount).' akun login baru berhasil dibangkitkan secara massal.');
    }

    public function generateAkun(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorize('siswa.edit');

        if ($siswa->user_id !== null) {
            return back()->withErrors(['user' => 'Siswa ini sudah memiliki akun login.']);
        }

        DB::transaction(function () use ($siswa) {
            $generator = app(AkunSiswaGenerator::class);
            $user = $generator->buat($siswa->nama_lengkap, $siswa->nis, $siswa->lembaga);
            if ($siswa->person) {
                $siswa->person->update(['user_id' => $user->id]);
            }
            $siswa->update(['user_id' => $user->id]);
        });

        return back()->with('status', "Akun login untuk {$siswa->nama_lengkap} berhasil dibuat (Username: {$siswa->refresh()->user->username}).");
    }

    private function validateSiswa(Request $request, ?Siswa $current = null): array
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer'],
            'nis' => [
                'required', 'string', 'max:30',
                function ($attribute, $value, $fail) use ($current) {
                    $exists = Siswa::withoutGlobalScopes()
                        ->where('lembaga_id', $current?->lembaga_id ?? auth()->user()->lembaga_id ?? session('active_lembaga_id'))
                        ->where('nis', $value)
                        ->when($current, fn ($query) => $query->where('id', '!=', $current->id))
                        ->exists();
                    if ($exists) {
                        $fail('NIS sudah dipakai siswa lain di lembaga ini.');
                    }
                },
            ],
            'nisn' => ['nullable', 'string', 'max:20'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'tempat_lahir' => ['nullable', 'string', 'max:255'],
            'tanggal_lahir' => ['nullable', 'date'],
            'agama' => ['nullable', 'string', 'max:50'],
        ]);

        if (! empty($data['kelas_id'])) {
            $lembagaId = $current?->lembaga_id ?? auth()->user()->lembaga_id ?? session('active_lembaga_id');
            $kelas = Kelas::find($data['kelas_id']);
            abort_if($kelas === null || $kelas->lembaga_id !== $lembagaId, 404);
            $data['kelas_id'] = $kelas->id;
        }

        return $data;
    }
}
