<?php

namespace App\Http\Controllers\Admin;

use App\Models\JenisKaryawanMaster;
use App\Models\Karyawan;
use App\Models\Lembaga;
use App\Models\Yayasan;
use App\Services\AkunKaryawanGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class KaryawanController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('karyawan.view');

        $search = $request->query('search');

        $karyawanList = Karyawan::with(['user', 'jenisKaryawan', 'lembaga'])
            ->when($search, fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->orderBy('nama')
            ->get();

        return view('admin.karyawan.index', [
            'karyawanList' => $karyawanList,
            'search' => $search,
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

        $karyawan->load(['user', 'jenisKaryawan', 'lembaga', 'yayasan']);

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

        $karyawan->user()->update(['name' => $data['nama']]);
        $karyawan->update($data);

        return redirect()->route('admin.karyawan.index')->with('status', 'Data karyawan berhasil diperbarui.');
    }

    public function updateStatus(Request $request, Karyawan $karyawan): RedirectResponse
    {
        $this->authorize('karyawan.edit');

        $data = $request->validate([
            'status_aktif' => ['required', 'in:aktif,non_aktif'],
        ]);

        $karyawan->update(['status_aktif' => $data['status_aktif']]);
        $karyawan->user()->update(['is_active' => $data['status_aktif'] === 'aktif']);

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
        return $request->validate([
            'nik' => ['required', 'digits:16', function ($attribute, $value, $fail) use ($karyawan) {
                $query = Karyawan::withoutGlobalScopes()->where('nik_hash', hash('sha256', $value));
                if ($karyawan) {
                    $query->where('id', '!=', $karyawan->id);
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
