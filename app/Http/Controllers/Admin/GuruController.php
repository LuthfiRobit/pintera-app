<?php

namespace App\Http\Controllers\Admin;

use App\Models\Guru;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class GuruController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('manage-guru');

        return view('admin.guru.index', ['guru' => Guru::with('user')->get()]);
    }

    public function create(): View
    {
        $this->authorize('manage-guru');

        $eligibleUsers = User::role('guru')->whereDoesntHave('guru')->get();

        return view('admin.guru.create', ['eligibleUsers' => $eligibleUsers]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('manage-guru');

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'nik' => ['required', 'digits:16'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'jenis_ptk' => ['required', 'in:guru_kelas,guru_mapel,kepala_sekolah,tenaga_administrasi'],
            'status_kepegawaian' => ['required', 'in:PNS,PPPK,GTY,PTY,Honorer'],
        ]);

        $targetUser = User::findOrFail($data['user_id']);

        Guru::create([
            ...$data,
            'lembaga_id' => $targetUser->lembaga_id,
        ]);

        return redirect()->route('admin.guru.index')->with('status', 'Data guru berhasil disimpan.');
    }

    public function edit(Guru $guru): View
    {
        $this->authorize('manage-guru');

        return view('admin.guru.edit', ['guru' => $guru]);
    }

    public function update(Request $request, Guru $guru): RedirectResponse
    {
        $this->authorize('manage-guru');

        $data = $request->validate([
            'nik' => ['required', 'digits:16'],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', 'in:L,P'],
            'jenis_ptk' => ['required', 'in:guru_kelas,guru_mapel,kepala_sekolah,tenaga_administrasi'],
            'status_kepegawaian' => ['required', 'in:PNS,PPPK,GTY,PTY,Honorer'],
        ]);

        $guru->update($data);

        return redirect()->route('admin.guru.index')->with('status', 'Data guru berhasil diperbarui.');
    }
}
