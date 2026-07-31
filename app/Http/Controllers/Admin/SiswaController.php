<?php

namespace App\Http\Controllers\Admin;

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

        $query = Siswa::with('kelas')->orderBy('nama_lengkap');

        // Search by name or NIS
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', '%' . $search . '%')
                    ->orWhere('nis', 'like', '%' . $search . '%');
            });
        }

        // Filter by Kelas
        if ($kelasId = $request->input('kelas_id')) {
            $query->where('kelas_id', $kelasId);
        }

        // Filter by Status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Build kelas list from the acting lembaga's active tahun ajaran only.
        // Left empty for a yayasan-scoped user with no active lembaga selected —
        // there is no single "the" active tahun ajaran to pick in that state.
        $lembagaId = $request->user()->lembaga_id ?? session('active_lembaga_id');
        $tahunAjaranAktif = $lembagaId ? TahunAjaran::where('status_aktif', true)->first() : null;
        $kelasList = $tahunAjaranAktif
            ? Kelas::where('tahun_ajaran_id', $tahunAjaranAktif->id)->orderBy('nama')->get()
            : collect();

        return view('admin.siswa.index', [
            'siswaList' => $query->paginate($perPage)->withQueryString(),
            'kelasList' => $kelasList,
            'statusList' => StatusSiswa::cases(),
            'perPage' => $perPage,
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
            $user = app(AkunSiswaGenerator::class)->buat($data['nama_lengkap'], $data['nis'], $lembaga);

            Siswa::create([...$data, 'user_id' => $user->id]);
        });

        return redirect()->route('admin.siswa.index')->with('status', 'Siswa & akun berhasil disimpan.');
    }

    public function edit(Siswa $siswa): View
    {
        $this->authorize('siswa.edit');

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
            if ($siswa->user_id) {
                $updates = [];
                if ($data['nama_lengkap'] !== $siswa->nama_lengkap) {
                    $updates['name'] = $data['nama_lengkap'];
                }
                if ($data['nis'] !== $siswa->nis) {
                    $updates['username'] = app(AkunSiswaGenerator::class)->usernameUntuk($siswa->lembaga, $data['nis']);
                }
                if ($updates !== []) {
                    $siswa->user()->update($updates);
                }
            }

            $siswa->update($data);
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
