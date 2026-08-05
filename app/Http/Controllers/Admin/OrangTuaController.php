<?php

namespace App\Http\Controllers\Admin;

use App\Models\OrangTua;
use App\Models\User;
use App\Services\AkunOrangTuaGenerator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class OrangTuaController extends BaseController
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        // Deliberate v1 decision: parent profiles are intentionally viewable yayasan-wide by
        // any orang-tua.view holder, not scoped to lembaga, because OrangTua has no
        // lembaga_id by design (a parent can have children in multiple lembaga).
        $this->authorize('orang-tua.view');

        $search = $request->query('search');

        $orangTuaList = OrangTua::with('user')
            ->withCount('siswa')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('nama_lengkap', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")))
            ->orderBy('nama_lengkap')
            ->get();

        return view('admin.orang-tua.index', [
            'orangTuaList' => $orangTuaList,
            'search' => $search,
            'totalOrangTua' => $orangTuaList->count(),
            'totalAktif' => $orangTuaList->filter(fn ($o) => $o->user && $o->user->is_active)->count(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('orang-tua.create');

        return view('admin.orang-tua.create');
    }

    public function store(Request $request, AkunOrangTuaGenerator $generator): RedirectResponse
    {
        $this->authorize('orang-tua.create');

        $data = $this->validateProfil($request);

        $existingUser = User::where('username', $data['nik'])->first();
        if ($existingUser) {
            $existingOrangTua = OrangTua::where('user_id', $existingUser->id)->first();

            if ($existingOrangTua === null) {
                return back()
                    ->withErrors(['nik' => 'NIK ini sudah terdaftar ke akun lain yang bukan profil Orang Tua.'])
                    ->withInput();
            }

            return redirect()
                ->route('admin.orang-tua.edit', $existingOrangTua)
                ->with('status', 'NIK sudah terdaftar — berikut profil Orang Tua yang sudah ada.');
        }

        $generator->buat(
            $data['nama_lengkap'],
            $data['nik'],
            $data['no_hp'],
            $data['email'] ?? null,
            $data['alamat'] ?? null,
            $data['pekerjaan'] ?? null,
        );

        return redirect()->route('admin.orang-tua.index')->with('status', 'Data Orang Tua & akun berhasil dibuat.');
    }

    public function edit(OrangTua $orangTua): View
    {
        // Deliberate v1 decision: parent profiles are intentionally editable yayasan-wide by
        // any orang-tua.edit holder, not scoped to lembaga, because OrangTua has no
        // lembaga_id by design (a parent can have children in multiple lembaga).
        $this->authorize('orang-tua.edit');

        $orangTua->load(['user', 'siswa']);

        return view('admin.orang-tua.edit', ['orangTua' => $orangTua]);
    }

    public function update(Request $request, OrangTua $orangTua): RedirectResponse
    {
        $this->authorize('orang-tua.edit');

        $data = $request->validate([
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
        ]);

        $orangTua->user()->update(['name' => $data['nama_lengkap']]);
        $orangTua->update($data);

        return redirect()->route('admin.orang-tua.index')->with('status', 'Data Orang Tua berhasil diperbarui.');
    }

    public function updateStatus(Request $request, OrangTua $orangTua): RedirectResponse
    {
        $this->authorize('orang-tua.edit');

        $data = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $orangTua->user()->update(['is_active' => $data['is_active']]);

        return redirect()->route('admin.orang-tua.index')->with('status', 'Status akun Orang Tua berhasil diperbarui.');
    }

    private function validateProfil(Request $request): array
    {
        return $request->validate([
            'nik' => ['required', 'digits:16'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'no_hp' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'alamat' => ['nullable', 'string'],
            'pekerjaan' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
