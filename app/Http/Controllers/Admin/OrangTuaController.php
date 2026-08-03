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
        $this->authorize('orang-tua.view');

        $search = $request->query('search');

        $orangTuaList = OrangTua::with('user')
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('nama_lengkap', 'like', "%{$search}%")
                ->orWhere('nik', 'like', "%{$search}%")))
            ->orderBy('nama_lengkap')
            ->get();

        return view('admin.orang-tua.index', [
            'orangTuaList' => $orangTuaList,
            'search' => $search,
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
